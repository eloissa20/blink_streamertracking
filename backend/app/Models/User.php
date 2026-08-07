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

    public function statsFmConnection()
    {
        return $this->hasOne(StatsFmConnection::class);
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
        return $this->statsFmConnection()->exists();
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
