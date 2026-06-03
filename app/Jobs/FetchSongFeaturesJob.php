<?php

namespace App\Jobs;

use App\Models\Song;
use App\Models\SongFeature;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Background job to generate AI-powered audio features for songs.
 *
 * This job takes a batch of the user's liked songs that don't yet have
 * audio features, sends each one to the Ollama LLM (LLaMA 3.2) for analysis,
 * and stores the resulting features (valence, energy, description, etc.)
 * in the `song_features` table.
 *
 * The job processes songs in batches of 20 and re-dispatches itself with
 * a 5-second delay if more songs remain, creating a self-sustaining pipeline
 * that won't overwhelm the LLM.
 *
 * This job is automatically chained after SyncSpotifySongsJob completes.
 */
class FetchSongFeaturesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum execution time in seconds.
     * Set high because LLM analysis can be slow per song.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\User  $user  The user whose songs need feature analysis
     */
    public function __construct(protected User $user) {}

    /**
     * Execute the job.
     *
     * Fetches up to 20 songs that lack features, analyzes each with the LLM,
     * and either re-dispatches itself for the next batch or marks the user
     * as no longer syncing.
     *
     * @return void
     */
    public function handle(): void
    {
        // Get the next batch of songs without features, prioritizing recent likes
        $songsToFetch = $this->user->likedSongs()
            ->whereDoesntHave('features')
            ->orderByPivot('spotify_added_at', 'desc')
            ->limit(20)
            ->get();

        // If all songs have been processed, mark sync as complete
        if ($songsToFetch->isEmpty()) {
            $this->user->update(['is_syncing' => false]);
            Log::info("Song DNA sync completed for {$this->user->name}");
            return;
        }

        // Analyze each song with the Ollama LLM
        foreach ($songsToFetch as $song) {
            $this->analyzeWithOllama($song);
            sleep(2);  // Rate-limit to avoid overwhelming the LLM
        }

        // If there are still more songs without features, re-dispatch with delay
        if ($this->user->likedSongs()->whereDoesntHave('features')->exists()) {
            self::dispatch($this->user)->delay(now()->addSeconds(5));
        } else {
            $this->user->update(['is_syncing' => false]);
            Log::info("All songs processed for {$this->user->name}.");
        }
    }

    /**
     * Analyze a single song using the Ollama LLM to generate audio features.
     *
     * Sends a carefully crafted prompt to LLaMA 3.2 that instructs the model
     * to act as a musicologist, analyze the song's genre/mood/instrumentation,
     * and return structured JSON with numerical audio features and a textual
     * description. The prompt includes a "logic matrix" to ensure consistency
     * between numerical values and the description.
     *
     * @param  \App\Models\Song  $song  The song to analyze
     * @return void
     */
    private function analyzeWithOllama(Song $song)
    {
        $url = env('OLLAMA_URL', 'http://host.docker.internal:11434') . '/api/chat';
        $model = env('OLLAMA_MODEL', 'llama3.2:3b');

        // System prompt: instructs the LLM to analyze the song and return structured JSON
        // with audio features that follow a strict valence/energy logic matrix
        $systemInstructions = "You are an expert Musicologist and Data Analyst. Analyze '{$song->title}' by '{$song->artist}'.

        STEP 1: Identify the song's actual genre, mood, and instrumentation based on your historical knowledge of the original recording. If the song is unknown, make a highly educated guess based on the artist's general style.
        STEP 2: Map this analysis to precise numerical values using the strict logic matrix below.

        CRITICAL LOGIC MATRIX (NEVER CONTRADICT THESE RULES):
        - VALENCE (Mood):
        * 0.0 to 0.3: Sad, Dark, Melancholic, Tragic. (FORBIDDEN words: Uplifting, Joyful, Euphoric).
        * 0.4 to 0.6: Neutral, Bittersweet, Chill, Mellow, Peaceful.
        * 0.7 to 1.0: Happy, Joyful, Uplifting, Euphoric. (FORBIDDEN words: Sad, Dark, Melancholic).
        - ENERGY (Intensity):
        * 0.0 to 0.3: Acoustic, Ambient, Lullaby, Calm. (FORBIDDEN words: Metal, Techno, Dance, Upbeat).
        * 0.4 to 0.6: Mid-tempo Pop, R&B, Soft Rock, Groovy.
        * 0.7 to 1.0: Hard Rock, Metal, Techno, Upbeat, Energetic. (FORBIDDEN words: Peaceful, Mellow, Calm).

        MANDATORY JSON STRUCTURE:
        {
            \"_thought_process\": \"Briefly explain the song's real genre, mood, and instruments in 1-2 sentences. Decide the V and E values here first based on the Matrix.\",
            \"valence\": [float],
            \"energy\": [float],
            \"danceability\": [float],
            \"acousticness\": [float],
            \"instrumentalness\": [float],
            \"speechiness\": [float],
            \"tempo\": [integer],
            \"loudness\": [float, e.g. -5.5],
            \"key\": [integer, 0-11],
            \"mode\": [integer, 0 or 1],
            \"description\": \"[Accurate Genre] song with [Mood] vibes, featuring [Actual Instruments].\"
        }

        Return ONLY JSON. Ensure the 'description' mood strictly obeys the V and E Matrix rules.";

        try {
            // Send the analysis request to Ollama with JSON format enforcement
            $response = Http::timeout(90)->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemInstructions],
                    ['role' => 'user', 'content' => "Provide JSON for '{$song->title}'."]
                ],
                'stream' => false,
                'format' => 'json',
                'options' => ['temperature' => 0.4]  // Lower temperature for more consistent results
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = json_decode($data['message']['content'] ?? '{}', true);

                if ($content) {
                    // Helper to safely extract and clamp numeric values to [0.0, 1.0]
                    $toNum = function($key, $default) use ($content) {
                            $val = $content[$key] ?? $default;
                            if (is_array($val)) $val = current($val);
                            return is_numeric($val) ? (float) min(1.0, max(0.0, $val)) : $default;
                        };

                        // Clean up the description field (remove array brackets, trim)
                        $description = $content['description'] ?? "{$song->artist} style track.";
                        if (is_array($description)) {
                            $description = implode(', ', $description);
                        }

                        $description = str_replace(['[', ']'], '', $description);
                        $description = ucfirst(trim($description));

                        // Upsert the feature record for this song
                        SongFeature::updateOrCreate(
                            ['song_id' => $song->id],
                            [
                                'valence'          => $toNum('valence', 0.5),
                                'energy'           => $toNum('energy', 0.5),
                                'danceability'     => $toNum('danceability', 0.5),
                                'acousticness'     => $toNum('acousticness', 0.5),
                                'instrumentalness' => $toNum('instrumentalness', 0.0),
                                'speechiness'      => $toNum('speechiness', 0.0),
                                'description'      => (string) $description,
                                'liveness'         => $toNum('liveness', 0.1),
                                'tempo'            => (int) (is_array($content['tempo'] ?? null) ? current($content['tempo']) : ($content['tempo'] ?? 120)),
                                'loudness'         => (float) (is_array($content['loudness'] ?? null) ? current($content['loudness']) : ($content['loudness'] ?? -10.0)),
                                'key'              => (int) (is_array($content['key'] ?? null) ? current($content['key']) : ($content['key'] ?? 0)),
                                'mode'             => (int) (is_array($content['mode'] ?? null) ? current($content['mode']) : ($content['mode'] ?? 1)),
                                'time_signature'   => 4,
                            ]
                        );

                    Log::info("Song DNA: {$song->title} [V:{$toNum('valence', 0.5)} E:{$toNum('energy', 0.5)}] -> \"{$description}\"");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error analyzing {$song->title}: " . $e->getMessage());
        }
    }
}