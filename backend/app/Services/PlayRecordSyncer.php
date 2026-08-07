<?php

namespace App\Services;

use App\Models\PlayRecord;
use App\Models\StatsFmConnection;
use App\Support\AllowedArtists;
use App\Support\StreamRules;

class PlayRecordSyncer
{
    public function __construct(private StatsFmService $statsFm) {}

    /**
     * Pull recent streams from Stats.fm and upsert them locally.
     * Idempotent — safe to call repeatedly. A play is only ever inserted
     * once, checked two ways:
     *   1. its statsfm_stream_id (the primary, DB-enforced-unique key), and
     *   2. as a fallback, the same connection + track + played_at, in case
     *      Stats.fm's API ever returns an inconsistent id for a play we've
     *      already recorded.
     * Every real stream is stored regardless of artist — "Recently played"
     * should reflect everything the account actually played. The
     * BLACKPINK/members-only restriction is applied later, only when
     * aggregating counts (PlayRecord::scopeAllowedArtists), not here.
     * Only plays from the connection's chosen service are stored — a
     * connection tracks exactly one of Spotify or Apple Music, never both.
     * And a play only counts as a stream at all if it meets the same
     * minimum-listen-duration rule regardless of source (StreamRules).
     */
    public function sync(StatsFmConnection $connection): int
    {
        $items = $this->statsFm->recentlyPlayed($connection->statsfm_user_id);
        $inserted = 0;

        foreach ($items as $item) {
            $normalized = $this->statsFm->normalizeStream($item);

            // Store BLACKPINK/members plays under one consistent spelling
            // regardless of what casing Stats.fm sent this particular
            // stream under (it isn't consistent — see
            // AllowedArtists::canonicalize()). Everything else keeps its
            // raw artist_name as-is; only allow-listed artists need a
            // canonical spelling, since only they get aggregated.
            $normalized['artist_name'] = AllowedArtists::canonicalize($normalized['artist_name'])
                ?? $normalized['artist_name'];

            // A connection tracks exactly one service. If the user connected
            // as Spotify, anything Stats.fm reports from Apple Music (or
            // vice versa) is ignored — and the reverse.
            if ($connection->connected_source && $normalized['source'] !== $connection->connected_source) {
                continue;
            }

            // Same minimum-duration rule for every source — see StreamRules.
            if (! StreamRules::countsAsStream($normalized['duration_ms'])) {
                continue;
            }

            $alreadyRecorded = PlayRecord::query()
                ->where('statsfm_stream_id', $normalized['statsfm_stream_id'])
                ->orWhere(function ($query) use ($connection, $normalized) {
                    $query->where('statsfm_connection_id', $connection->id)
                        ->where('track_id', $normalized['track_id'])
                        ->where('played_at', $normalized['played_at']);
                })
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            try {
                PlayRecord::create([
                    ...$normalized,
                    'user_id' => $connection->user_id,
                    'statsfm_connection_id' => $connection->id,
                ]);
                $inserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique constraint on statsfm_stream_id caught a race
                // (e.g. two overlapping sync runs for the same connection).
                // The record already exists, so this is not an error.
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
            }
        }

        $connection->forceFill(['last_synced_at' => now()])->save();

        return $inserted;
    }
}
