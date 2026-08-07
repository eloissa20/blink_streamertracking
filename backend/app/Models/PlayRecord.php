<?php

namespace App\Models;

use App\Support\AllowedArtists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PlayRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'statsfm_connection_id',
        'statsfm_stream_id',
        'musicat_connection_id',
        'musicat_stream_id',
        'track_id',
        'track_name',
        'artist_id',
        'artist_name',
        'album_name',
        'artwork_url',
        'artist_image_url',
        'source',
        'duration_ms',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function musicatConnection()
    {
        return $this->belongsTo(MusicatConnection::class);
    }

    /**
     * Constrain plays to a named window: day | week | month | year | all.
     */
    public function scopeInWindow(Builder $query, string $window): Builder
    {
        return match ($window) {
            'day' => $query->where('played_at', '>=', Carbon::now()->startOfDay()),
            'week' => $query->where('played_at', '>=', Carbon::now()->startOfWeek()),
            'month' => $query->where('played_at', '>=', Carbon::now()->startOfMonth()),
            'year' => $query->where('played_at', '>=', Carbon::now()->startOfYear()),
            default => $query,
        };
    }

    /**
     * Restrict to the BLACKPINK + members allow-list. Applied everywhere
     * plays are read, so anything outside the allow-list — even if it's
     * already sitting in the table from an older sync — is never counted.
     */
    public function scopeAllowedArtists(Builder $query): Builder
    {
        $names = AllowedArtists::lowerNames();
        $placeholders = implode(',', array_fill(0, count($names), '?'));

        return $query->whereRaw("LOWER(artist_name) IN ($placeholders)", $names);
    }

    /**
     * A connection tracks exactly one service. This is a read-time safety
     * net alongside the same check already applied when a play is synced
     * in — so even a play left over from before a user switched services
     * (or from before this feature existed) is excluded from every count
     * and list.
     *
     * Two kinds of connection can back a play now:
     *  - Stats.fm connections, which (post-Musicat-migration) only ever
     *    track Spotify. Legacy rows with no `connected_source` yet are
     *    left unrestricted rather than dropped.
     *  - Musicat connections, which are the exclusive source for Apple
     *    Music and always match `source = apple_music`.
     */
    public function scopeMatchingConnectedSource(Builder $query): Builder
    {
        return $query->where(function ($outer) {
            $outer->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('statsfm_connections')
                    ->whereColumn('statsfm_connections.id', 'play_records.statsfm_connection_id')
                    ->where(function ($q) {
                        $q->whereColumn('statsfm_connections.connected_source', 'play_records.source')
                            ->orWhereNull('statsfm_connections.connected_source');
                    });
            })->orWhereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('musicat_connections')
                    ->whereColumn('musicat_connections.id', 'play_records.musicat_connection_id')
                    ->where('play_records.source', 'apple_music');
            });
        });
    }
}
