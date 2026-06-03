<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the songs table.
 *
 * Stores metadata for songs imported from the Spotify API.
 * Each song is uniquely identified by its Spotify track ID
 * and contains basic information like title, artist, album, and cover image.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_track_id')->unique();  // Spotify's unique track identifier
            $table->string('title');                        // Song title
            $table->string('artist');                       // Primary artist name
            $table->string('album_name')->nullable();       // Album name (optional)
            $table->string('image')->nullable();            // Album cover image URL (optional)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
