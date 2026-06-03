<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the song_features table.
 *
 * Stores AI-generated audio features for each song. These features are
 * produced by the FetchSongFeaturesJob, which sends each song to the
 * Ollama LLM for musicological analysis. The values mirror Spotify's
 * Audio Features schema but are AI-estimated.
 *
 * Key columns used by the recommendation engine:
 *   - valence (0.0-1.0): Musical positivity/happiness
 *   - energy (0.0-1.0): Perceptual intensity and activity
 *   - description: Natural-language text used for vector embedding in FAISS
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('song_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->onDelete('cascade');

            // Core audio features (0.0 to 1.0 scale)
            $table->float('danceability')->nullable();       // Suitability for dancing
            $table->float('energy')->nullable();             // Intensity and activity
            $table->float('valence')->nullable();            // Musical positivity
            $table->float('acousticness')->nullable();       // Acoustic vs. electronic
            $table->float('instrumentalness')->nullable();   // Vocal vs. instrumental

            // Technical audio properties
            $table->float('tempo')->nullable();              // Estimated BPM
            $table->float('speechiness')->nullable();        // Presence of spoken words
            $table->float('liveness')->nullable();           // Audience presence likelihood
            $table->float('loudness')->nullable();           // Overall loudness (dB)
            $table->integer('key')->nullable();              // Musical key (0=C to 11=B)
            $table->integer('mode')->nullable();             // Major (1) or minor (0)
            $table->integer('time_signature')->nullable();   // Beats per measure
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_features');
    }
};
