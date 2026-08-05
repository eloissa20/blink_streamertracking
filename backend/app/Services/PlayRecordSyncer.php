<?php

namespace App\Services;

use App\Models\PlayRecord;
use App\Models\StatsFmConnection;
use App\Support\AllowedArtists;

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
     * Only BLACKPINK + members plays are stored; anything else is skipped.
     */
    public function sync(StatsFmConnection $connection): int
    {
        $items = $this->statsFm->recentlyPlayed($connection->statsfm_user_id);
        $inserted = 0;

        foreach ($items as $item) {
            $normalized = $this->statsFm->normalizeStream($item);

            if (! AllowedArtists::isAllowed($normalized['artist_name'])) {
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