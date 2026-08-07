<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's linked Musicat (https://musicat.fm) account — the exclusive
 * source for Apple Music stats in this app. Spotify continues to come from
 * a separate StatsFmConnection; a user may have one of each at the same
 * time, since they now cover two different services rather than one
 * account covering both.
 */
class MusicatConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'musicat_user_id',
        'musicat_username',
        'display_name',
        'avatar_url',
        'include_in_public_overview',
        'connected_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'include_in_public_overview' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function playRecords()
    {
        return $this->hasMany(PlayRecord::class);
    }
}
