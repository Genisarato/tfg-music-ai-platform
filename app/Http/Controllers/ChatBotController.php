<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;

/**
 * Manages the AI-powered music chatbot interface.
 *
 * This controller handles three core operations:
 * 1. Rendering the chatbot conversation page
 * 2. Proxying user messages to the Python AI microservice for recommendations
 * 3. Saving recommended songs to the user's Spotify library (like)
 */
class ChatbotController extends Controller
{
    /**
     * Display the chatbot page.
     *
     * Renders the chatBot/Index Vue component via Inertia.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('chatBot/Index', [
            'title' => 'Asistente Musical'
        ]);
    }

    /**
     * Forward a user message to the AI service and return song recommendations.
     *
     * Sends the user's natural-language query to the Python FastAPI microservice
     * at /search, which performs semantic vector search with FAISS and generates
     * a conversational reply via Ollama. The recommended song IDs are then
     * resolved to full Song models from the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {
        $request->validate(['message' => 'required|string']);

        try {
            // Call the Python AI microservice running inside the Docker network
            $response = Http::timeout(60)->post('http://ai-service:8000/search', [
                'text' => $request->message,
                'limit' => 3,
                'user_id' => Auth::id(),
            ]);

            if ($response->failed()) {
                throw new \Exception("AI Service returned error: " . $response->status());
            }

            $data = $response->json();
            $aiReply = $data['ai_reply'] ?? "Here are some recommendations based on your request:";
            $recommendedIds = $data['recommended_ids'] ?? [];

            // If no recommendations were found, return the AI reply with an empty list
            if (empty($recommendedIds)) {
                return response()->json([
                    'reply' => $aiReply,
                    'songs' => []
                ]);
            }

            // Fetch full song records from the DB, preserving the AI's ranking order
            $songs = Song::whereIn('id', $recommendedIds)
                ->orderByRaw('FIELD(id, ' . implode(',', $recommendedIds) . ')')
                ->get();

            return response()->json([
                'reply' => $aiReply,
                'songs' => $songs
            ]);

        } catch (\Exception $e) {
            \Log::error("Chatbot Error: " . $e->getMessage());
            return response()->json([
                'reply' => "A connection error has occurred.",
                'songs' => []
            ], 500);
        }
    }

    /**
     * Save a recommended song to the user's Spotify library.
     *
     * Uses the stored Spotify access token to add the track to the user's
     * Spotify "Liked Songs" via the Web API, then records the relationship
     * in the local `likes` pivot table.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function like(Request $request)
    {
        $request->validate(['song_id' => 'required|integer']);

        $user = Auth::user();
        $song = \App\Models\Song::findOrFail($request->song_id);
        $token = session('spotify_token');

        // Build the Spotify track URI format required by the API
        $spotifyUri = "spotify:track:{$song->spotify_track_id}";

        try {
            // Add the track to the user's Spotify "Liked Songs" library
            $response = Http::withToken($token)
                ->put("https://api.spotify.com/v1/me/library?uris={$spotifyUri}");

            if ($response->successful()) {
                // Record the like in the local database pivot table
                $user->likedSongs()->attach($song->id, [
                    'spotify_added_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                return response()->json(['message' => 'Saved to your library!']);
            }

            return response()->json([
                'error' => 'Spotify rejected the request',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}