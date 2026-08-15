<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_canonical',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * All of this user's linked Stats.fm (Spotify) accounts. Was a
     * hasOne — a user could only ever link a single Spotify account — but
     * the multi-account feature lifts that to a hasMany (see the
     * 2026_05_01_000000_allow_multiple_statsfm_connections migration) so
     * one system user can connect 5+ Spotify accounts at once, each
     * tracked as its own connection with fully isolated plays/stats.
     */
    public function statsFmConnections()
    {
        return $this->hasMany(StatsFmConnection::class);
    }

    /**
     * The connection to treat as "active" when a request doesn't name one
     * explicitly (e.g. an older client, or a fresh page load before the
     * user has picked an account). Deterministic — always the earliest-
     * connected account — rather than "whichever the DB happens to return
     * first", so which account's stats show up by default never flickers
     * between requests. Never combines multiple connections' data; it
     * only ever picks one.
     */
    public function defaultStatsFmConnection()
    {
        return $this->statsFmConnections()->oldest('connected_at')->first();
    }

    public function musicatConnection()
    {
        return $this->hasOne(MusicatConnection::class);
    }

    public function playRecords()
    {
        return $this->hasMany(PlayRecord::class);
    }

    public function hasConnectedStatsFm(): bool
    {
        return $this->statsFmConnections()->exists();
    }

    public function hasConnectedMusicat(): bool
    {
        return $this->musicatConnection()->exists();
    }

    /** True once the user has linked at least one listening source. */
    public function hasAnyConnection(): bool
    {
        return $this->hasConnectedStatsFm() || $this->hasConnectedMusicat();
    }
}