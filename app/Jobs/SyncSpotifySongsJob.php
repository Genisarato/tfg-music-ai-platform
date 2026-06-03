<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Song;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Jobs\FetchSongFeaturesJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Background job to sync a user's liked songs from the Spotify API.
 *
 * This job paginates through the Spotify "Saved Tracks" endpoint (/me/tracks),
 * upserts each track into the local `songs` table, and records the user-song
 * relationship in the `likes` pivot table with the original `added_at` date.
 *
 * After all songs are synced, it dispatches a FetchSongFeaturesJob to analyze
 * each song with the AI (Ollama) and generate audio feature metadata.
 *
 * This job is dispatched:
 * - Automatically after Spotify OAuth login (SpotifyController::callback)
 * - Manually via the "Sync" button (SongController::sync)
 */
class SyncSpotifySongsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\User  $user  The user whose Spotify library to sync
     */
    public function __construct(protected User $user) {}

    /**
     * Execute the job.
     *
     * Iterates through all pages of the user's Spotify saved tracks (50 per page),
     * upserts songs, syncs the likes pivot table, then chains the feature analysis job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Mark the user as syncing so the UI can show a loading indicator
        $this->user->update(['is_syncing' => true]);

        $offset = 0;
        $limit = 50;      // Spotify's maximum per-page limit
        $maxSongs = 600;  // Límit de les últimes cançons afegides que volem obtenir
        $hasMore = true;
        $allSongsPayload = [];  // Accumulates song_id => pivot data

        try {
            // Paginate through all saved tracks from the Spotify API
            while ($hasMore) {
                $response = Http::withToken($this->user->access_token)
                    ->get("https://api.spotify.com/v1/me/tracks", [
                        'limit' => $limit,
                        'offset' => $offset,
                    ]);

                if ($response->failed()) {
                    Log::error("Spotify API error for user {$this->user->id}: " . $response->body());
                    break;
                }

                $data = $response->json();

                foreach ($data['items'] as $item) {
                    $track = $item['track'];

                    // Upsert the song: create if new, update if exists (by Spotify track ID)
                    $song = Song::updateOrCreate(
                        ['spotify_track_id' => $track['id']],
                        [
                            'title' => $track['name'],
                            'artist' => $track['artists'][0]['name'],
                            'album_name' => $track['album']['name'] ?? null,
                            'image' => $track['album']['images'][0]['url'] ?? null,
                        ]
                    );

                    // Prepare the pivot data with the original Spotify added_at timestamp
                    $allSongsPayload[$song->id] = [
                        'spotify_added_at' => Carbon::parse($item['added_at'])->toDateTimeString()
                    ];
                }

                // Lògica de paginació amb el límit de 600
                $offset += $limit;
                
                // Comprovem si hi ha una pàgina següent I si no hem superat el nostre límit màxim
                if (isset($data['next']) && $offset < $maxSongs) {
                    $hasMore = true;
                } else {
                    $hasMore = false;
                }
            }

            // Sincronitza sense esborrar: afegeix les noves i actualitza les existents,
            // però manté intactes les cançons antigues a la base de dades local.
            if (!empty($allSongsPayload)) {
                $this->user->likedSongs()->syncWithoutDetaching($allSongsPayload);
            }

            // Chain the next job: analyze songs with AI to generate audio features
            FetchSongFeaturesJob::dispatch($this->user);

        } catch (\Exception $e) {
            Log::error("Error syncing songs for user {$this->user->id}: " . $e->getMessage());
        }
    }
}