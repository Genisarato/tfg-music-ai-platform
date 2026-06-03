<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Models\Recommendation;

/**
 * Represents an authenticated user of the application.
 *
 * Users authenticate via Spotify OAuth and their profile data (name, email,
 * avatar) is synced from Spotify. The model also stores Spotify API tokens
 * for making authenticated requests on behalf of the user. Supports optional
 * two-factor authentication via Laravel Fortify.
 *
 * @property int         $id
 * @property string      $name            Display name from Spotify
 * @property string      $email           Email address from Spotify
 * @property string|null $password        Optional password (may be null for Spotify-only users)
 * @property string|null $spotify_id      Unique Spotify user ID
 * @property string|null $access_token    Spotify OAuth access token
 * @property string|null $refresh_token   Spotify OAuth refresh token
 * @property string|null $avatar          Profile picture URL from Spotify
 * @property bool        $is_syncing      Whether a song sync job is currently running
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'spotify_id',
        'access_token',
        'refresh_token',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * Prevents sensitive data from being exposed in API responses or JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the recommendations generated for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Get the songs liked by this user.
     *
     * Many-to-many relationship via the `likes` pivot table. Includes
     * the `spotify_added_at` timestamp from the pivot and automatic
     * timestamp management.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function likedSongs()
    {
        return $this->belongsToMany(Song::class, 'likes')
                ->withPivot('spotify_added_at')
                ->withTimestamps();
    }
}
