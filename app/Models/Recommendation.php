<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracks individual song recommendations made to a user.
 *
 * Each record represents a single song that was recommended to a user
 * in a specific context (e.g., chatbot query). Optionally tracks whether
 * the user liked the recommendation.
 *
 * @property int         $id
 * @property int         $user_id        The user who received the recommendation
 * @property int         $song_id        The recommended song
 * @property string|null $context        The query or context that generated this recommendation
 * @property bool|null   $liked_by_user  Whether the user liked/saved this recommendation
 */
class Recommendation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'song_id',
        'context',
        'liked_by_user',
    ];

    /**
     * Get the user who received this recommendation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the song that was recommended.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function song()
    {
        return $this->belongsTo(Song::class);
    }
}