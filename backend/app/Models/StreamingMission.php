<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StreamingMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'track_id',
        'track_name',
        'artist_name',
        'artwork_url',
        'target_streams',
        'theme_key',
        'source',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'target_streams' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isPerSong(): bool
    {
        return ! empty($this->track_id) || ! empty($this->track_name);
    }

    /**
     * Missions currently open for contribution: flagged active, and
     * (if set) within their start/end window.
     */
    public function scopeCurrentlyOpen(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Every play across the whole app (not just the requesting user) that
     * counts toward this mission — a per-song mission matches on
     * track_name (case-insensitive; track_id can differ across sources/
     * regions for the same song), an artist-wide mission matches on
     * artist_name alone.
     *
     * If `source` is set (see the migration that added it), a mission
     * only counts plays from that one platform — a "Heaven — Spotify"
     * mission and a "Heaven — Apple Music" mission are two separate rows
     * with two separate source values, so a stream on one platform never
     * adds to the other's progress bar, even though both still pool
     * every user's plays on that platform (this isn't per-user — see
     * StreamingMissionController::index()). Left null on any older
     * mission row that predates this column, which keeps counting both
     * platforms combined exactly as before.
     */
    public function matchingPlaysQuery()
    {
        $query = PlayRecord::query()->matchingConnectedSource();

        if ($this->source) {
            $query->where('source', $this->source);
        }

        if ($this->isPerSong()) {
            $query->whereRaw('UPPER(track_name) = ?', [mb_strtoupper($this->track_name)]);
            if ($this->artist_name) {
                $query->whereRaw('UPPER(artist_name) = ?', [mb_strtoupper($this->artist_name)]);
            }
        } else {
            $query->whereRaw('UPPER(artist_name) = ?', [mb_strtoupper($this->artist_name)]);
        }

        return $query;
    }
}
