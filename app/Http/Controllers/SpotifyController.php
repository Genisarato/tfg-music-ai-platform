<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Handles the Spotify OAuth 2.0 authentication flow.
 *
 * This controller manages three main responsibilities:
 * 1. Redirecting the user to Spotify's authorization page
 * 2. Processing the OAuth callback to exchange the code for tokens
 * 3. Logging the user out and clearing the session
 */
class SpotifyController extends Controller
{
    /**
     * Redirect the user to the Spotify authorization page.
     *
     * Generates a random state token for CSRF protection and builds
     * the OAuth URL with the required scopes for reading/writing
     * the user's Spotify library.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect()
    {
        $state = Str::random(16);

        // Scopes required to read the user's profile and manage their library
        $scopes = 'user-read-private user-read-email user-library-read user-library-modify';

        $query = http_build_query([
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'response_type' => 'code',
            'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
            'state' => $state,
            'scope' => $scopes,
            'show_dialog' => 'true'
        ]);

        return redirect('https://accounts.spotify.com/authorize?' . $query);
    }

    /**
     * Handle the Spotify OAuth callback.
     *
     * Exchanges the authorization code for access/refresh tokens, fetches
     * the user's Spotify profile, creates or updates the local User record,
     * logs them in via Laravel's Auth system, and dispatches a background
     * job to sync their liked songs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|string
     */
    public function callback(Request $request)
    {
        // Extract the authorization code provided by Spotify
        $code = $request->input('code');

        // Exchange the authorization code for access and refresh tokens
        $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => env('SPOTIFY_REDIRECT_URI'),
            'client_id'     => env('SPOTIFY_CLIENT_ID'),
            'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token'];

            // Fetch the authenticated user's Spotify profile
            $userResponse = Http::withToken($accessToken)->get('https://api.spotify.com/v1/me');

            if ($userResponse->successful()) {
                $userData = $userResponse->json();

                // Create or update the user record using spotify_id as unique key
                $user = User::updateOrCreate(
                    ['spotify_id' => $userData['id']],
                    [
                        'name'          => $userData['display_name'],
                        'email'         => $userData['email'],
                        'avatar'        => $userData['images'][0]['url'] ?? null,
                        'access_token'  => $accessToken,
                        'refresh_token' => $refreshToken,
                    ]
                );

                // Log the user in using Laravel's session-based authentication
                Auth::login($user);

                // Dispatch background job to sync liked songs from Spotify
                // This runs asynchronously to avoid blocking the user experience
                \App\Jobs\SyncSpotifySongsJob::dispatch($user);

                // Store the access token in session for subsequent Spotify API calls
                session(['spotify_token' => $accessToken]);

                return redirect()->route('chatbot');
            }

            return redirect()->route('chatbot');
        }

        return "Authentication error: " . $response->body();
    }

    /**
     * Log the user out and invalidate their session.
     *
     * Clears the Laravel authentication state, invalidates the session
     * to prevent session fixation attacks, and regenerates the CSRF token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // Invalidate session and regenerate CSRF token for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}