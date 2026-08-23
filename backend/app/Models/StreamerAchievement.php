<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamerAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'achievement_key',
        'type',
        'artist_name',
        'member_name',
        'song_title',
        'image_url',
        'level',
        'tier',
        'total_streams_at_unlock',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'total_streams_at_unlock' => 'integer',
            'achieved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
