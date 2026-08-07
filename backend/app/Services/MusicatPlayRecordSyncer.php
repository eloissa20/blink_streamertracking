<?php

namespace App\Services;

use App\Models\MusicatConnection;
use App\Models\PlayRecord;
use App\Support\AllowedArtists;
use App\Support\StreamRules;

/**
 * Pull recent Apple Music plays from Musicat and upsert them locally.
 * Mirrors PlayRecordSyncer's Stats.fm logic exactly, but writes
 * musicat_connection_id / musicat_stream_id instead, and every row is
 * source = apple_music since that's all a Musicat connection ever is here.
 */
class MusicatPlayRecordSyncer
{
    public function __construct(private MusicatService $musicat) {}

    public function sync(MusicatConnection $connection): int
    {
        $items = $this->musicat->recentlyPlayed($connection->musicat_user_id);
        $inserted = 0;

        // Recently-played rows never show a photo, but the profile's "Top
        // artists" section does — fetch that mapping once per sync run
        // (rather than once per item) and use it to fill artist_image_url,
        // which normalizeStream() otherwise always leaves null.
        //
        // Keyed by UPPER(TRIM(name)) rather than the raw name: the
        // "Recently played" rows and the "Top artists" panel are two
        // separate places on the same musicat.fm page, and they don't
        // always render an artist's name with identical casing (e.g.
        // "LiSA" in one row, "LISA" in the other). An exact-string lookup
        // silently misses in that case and the play keeps a null image
        // forever, since nothing re-checks it after the fact.
        $artistImages = [];
        foreach ($this->musicat->artistImageMap($connection->musicat_user_id) as $name => $url) {
            $artistImages[strtoupper(trim($name))] = $url;
        }

        foreach ($items as $item) {
            $normalized = $this->musicat->normalizeStream($item);

            if (! AllowedArtists::isAllowed($normalized['artist_name'])) {
                continue;
            }

            if (! StreamRules::countsAsStream($normalized['duration_ms'])) {
                continue;
            }

            $imageKey = strtoupper(trim($normalized['artist_name']));
            $normalized['artist_image_url'] = $artistImages[$imageKey] ?? null;

            $alreadyRecorded = PlayRecord::query()
                ->where('musicat_stream_id', $normalized['musicat_stream_id'])
                ->orWhere(function ($query) use ($connection, $normalized) {
                    $query->where('musicat_connection_id', $connection->id)
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
                    'musicat_connection_id' => $connection->id,
                ]);
                $inserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique constraint on musicat_stream_id caught a race
                // (e.g. two overlapping sync runs for the same connection).
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
            }
        }

        $connection->forceFill(['last_synced_at' => now()])->save();

        return $inserted;
    }
}