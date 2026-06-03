<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a song imported from the Spotify API.
 *
 * Each song is uniquely identified by its `spotify_track_id` and stores
 * basic metadata (title, artist, album, image). Songs are linked to users
 * through the `likes` pivot table and can have AI-generated audio features
 * attached via the `song_features` table.
 *
 * @property int    $id
 * @property string $spotify_track_id  Spotify's unique track identifier
 * @property string $title             Song title
 * @property string $artist            Primary artist name
 * @property string|null $album_name   Album name
 * @property string|null $image        Album cover image URL
 * @property string $spotify_url       Computed Spotify web player URL
 */
class Song extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'spotify_track_id',
        'title',
        'artist',
        'album_name',
        'image',
    ];

    /**
     * Computed attributes appended to JSON serialization.
     *
     * @var array<int, string>
     */
    protected $appends = ['spotify_url'];

    /**
     * Get the recommendations associated with this song.
     *
     * A song can appear in multiple recommendation results for different users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Get the users who have liked this song.
     *
     * Many-to-many relationship via the `likes` pivot table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'likes');
    }

    /**
     * Get the AI-generated audio features for this song.
     *
     * Each song has at most one SongFeature record containing values like
     * valence, energy, danceability, and a natural-language description.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function features()
    {
        return $this->hasOne(SongFeature::class);
    }

    /**
     * Generate the Spotify web player URL for this track.
     *
     * @return string  Full URL to the song on Spotify's web player
     */
    public function getSpotifyUrlAttribute()
    {
        return "https://open.spotify.com/track/{$this->spotify_track_id}";
    }
}