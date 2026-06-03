<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

/**
 * Manages the user's liked songs library.
 *
 * Provides a paginated, searchable, and sortable view of the user's
 * synced Spotify liked songs, and allows triggering a manual re-sync
 * from Spotify.
 */
class SongController extends Controller
{
    /**
     * Display the user's liked songs library.
     *
     * Supports two sort modes:
     * - 'recent' (default): ordered by Spotify's `added_at` date, newest first
     * - 'az': alphabetically by song title
     *
     * Also supports a search filter that matches against both title and artist.
     * Results are paginated (20 per page) and query strings are preserved.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $sort = $request->input('sort', 'recent');
        $search = $request->input('search');

        // Build the query: join songs with the likes pivot table
        $query = $user->likedSongs()
            ->select('songs.*', 'likes.spotify_added_at as liked_at');

        // Apply optional search filter across title and artist
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('artist', 'like', "%{$search}%");
            });
        }

        // Apply sorting: alphabetical or by recency
        if ($sort === 'az') {
            $query->orderBy('songs.title', 'asc');
        } else {
            $query->orderBy('likes.spotify_added_at', 'desc');
        }

        // Paginate results and preserve query string parameters in pagination links
        $songs = $query->paginate(20)->withQueryString();

        return Inertia::render('likedSongs/Index', [
            'songs' => $songs,
            'isSyncing' => (bool) $user->is_syncing,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ]
        ]);
    }

    /**
     * Trigger a manual re-sync of the user's Spotify library.
     *
     * Dispatches a SyncSpotifySongsJob in the background. If a sync
     * is already in progress (`is_syncing` is true), the request is
     * silently ignored to prevent duplicate jobs.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sync()
    {
        $user = auth()->user();

        // Prevent duplicate sync jobs
        if ($user->is_syncing) {
            return back();
        }

        $user->update(['is_syncing' => true]);

        \App\Jobs\SyncSpotifySongsJob::dispatch($user);

        return back()->with('message', 'Sync started');
    }
}