<?php

namespace App\Services;

use App\Models\StatsFmConnection;
use App\Support\AllowedArtists;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client around the Stats.fm public API (https://beta.docs.stats.fm/).
 *
 * Stats.fm is a third-party service that itself aggregates a listener's
 * Spotify and/or Apple Music history, so a single Stats.fm account is our
 * one source of truth for both platforms — we never talk to Spotify/Apple
 * directly.
 */
class StatsFmService
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('statsfm.base_url');
        $this->apiKey = config('statsfm.api_key');
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withHeaders(array_filter([
                'Authorization' => $this->apiKey ? "Bearer {$this->apiKey}" : null,
            ]))
            ->timeout(15);
    }

    /**
     * Look up a public Stats.fm profile by handle, used when a user connects
     * their account for the first time (they type their Stats.fm username).
     */
    public function findUserByHandle(string $handle): ?array
    {
        $response = $this->client()->get("/users/{$handle}");

        if ($response->failed()) {
            Log::warning('StatsFm findUserByHandle failed', [
                'handle' => $handle,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->json('item');
    }

    /**
     * Fetch recently played tracks for a connected Stats.fm user.
     * Returns raw stream items — normalized/persisted by PlayRecordSyncer.
     */
    public function recentlyPlayed(string $statsfmUserId, int $limit = 100): array
    {
        $response = $this->client()->get("/users/{$statsfmUserId}/streams/recent", [
            'limit' => $limit,
        ]);

        if ($response->failed()) {
            Log::warning('StatsFm recentlyPlayed failed', [
                'statsfm_user_id' => $statsfmUserId,
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json('items', []);
    }

    /**
     * Normalize a raw Stats.fm stream item into the shape PlayRecord expects.
     * Source platform is inferred from the track's `externalIds` /
     * `platform` field that Stats.fm attaches to each stream.
     */
    public function normalizeStream(array $item): array
    {
        $track = $item['track'] ?? [];
        $artist = $this->attributedArtist($track['artists'] ?? []);

        $platform = strtolower($item['platform'] ?? 'spotify');
        $source = str_contains($platform, 'apple') ? 'apple_music' : 'spotify';

        // Normalize to a Carbon instance (not a raw string) right here, once.
        // This is the single source of truth for played_at used both when
        // the record is inserted and when PlayRecordSyncer checks for an
        // existing record — keeping them as raw, differently-formatted
        // strings was causing the duplicate-detection query to never match,
        // so repeat syncs re-inserted the same play.
        //
        // IMPORTANT: when Stats.fm doesn't send a real `endTime`/`range.end`
        // for an item, we do NOT fall back to `now()`. Doing that used to
        // stamp every such item with the exact moment the sync happened —
        // so a batch of several tracks missing this field all landed on
        // the *same* timestamp (the sync run's clock time), which showed
        // up in "Recently Played" as multiple different songs all "played"
        // at the identical minute/second. That's not a real play time, so
        // rather than invent one we leave `played_at` null here and let
        // PlayRecordSyncer skip the item — better to sync it on a later
        // pass once Stats.fm actually reports a real timestamp for it than
        // to store a fabricated one that corrupts ordering and stats.
        $rawPlayedAt = $item['endTime'] ?? $item['range']['end'] ?? null;
        $playedAt = $rawPlayedAt !== null ? Carbon::parse($rawPlayedAt) : null;

        return [
            'statsfm_stream_id' => (string) (
                $item['id']
                ?? $item['range']['start']
                ?? $this->derivedStreamId($track['id'] ?? '', $playedAt?->toIso8601String() ?? 'unknown', $platform)
            ),
            'track_id' => (string) ($track['id'] ?? ''),
            'track_name' => $track['name'] ?? 'Unknown Track',
            'artist_id' => (string) ($artist['id'] ?? ''),
            'artist_name' => $artist['name'] ?? 'Unknown Artist',
            'album_name' => $track['albums'][0]['name'] ?? null,
            'artwork_url' => $track['albums'][0]['image'] ?? null,
            'artist_image_url' => $artist['image'] ?? null,
            'source' => $source,
            'duration_ms' => (int) ($item['playedMs'] ?? $track['durationMs'] ?? 0),
            'played_at' => $playedAt,
        ];
    }

    /**
     * Pick which of a track's credited artists this play should be
     * attributed to. Stats.fm (mirroring Spotify) lists every credited
     * artist on a track in `artists`, ordered primary-artist-first — but
     * "primary" just means top-billed, not "the only one who matters
     * here". A track where an allowed artist (e.g. LISA) is a *featured*
     * guest on someone else's track — "Priceless (feat. LISA)", credited
     * as artists: [ELLA JAY, LISA] — has her at index 1, not 0.
     *
     * Blindly taking artists[0] (the old behavior) attributed every such
     * play to the primary artist instead, who then never matches the
     * BLACKPINK allow-list — so the play was recorded, but effectively
     * vanished: never counted, never shown as one of LISA's plays,
     * anywhere. This scans the full artist list and prefers whichever
     * credited artist is actually on the allow-list, so a featured
     * appearance by an allowed artist is still attributed to her. Falls
     * back to the primary artist when none of the credits are on the
     * allow-list (that play gets filtered out downstream regardless, so
     * which non-allowed artist it's nominally attributed to doesn't
     * matter).
     */
    private function attributedArtist(array $artists): array
    {
        foreach ($artists as $candidate) {
            if (AllowedArtists::isAllowed($candidate['name'] ?? null)) {
                return $candidate;
            }
        }

        return $artists[0] ?? [];
    }

    /**
     * Deterministic fallback stream id, used only when Stats.fm doesn't
     * give us a stable `id` or `range.start` for a stream item. Built from
     * fields that describe the play event itself (track + when it ended +
     * platform), so re-syncing the exact same play always produces the
     * exact same id — unlike uniqid(), which is different every call and
     * was previously causing the same play to be re-inserted on every sync.
     */
    private function derivedStreamId(string $trackId, string $playedAt, string $platform): string
    {
        return 'derived_' . md5($trackId . '|' . $playedAt . '|' . $platform);
    }
}