<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatsFmConnection extends Model
{
    use HasFactory;

    // Eloquent's naming convention would guess `stats_fm_connections` from
    // this class name (it treats "Fm" as its own word), but the migration
    // actually created the table as `statsfm_connections` — spelled as one
    // word. Without this, every query against this model fails with
    // "table doesn't exist".
    protected $table = 'statsfm_connections';

    protected $fillable = [
        'user_id',
        'statsfm_user_id',
        'statsfm_username',
        'display_name',
        'avatar_url',
        'label',
        'connected_source',
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