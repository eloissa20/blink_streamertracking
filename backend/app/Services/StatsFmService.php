<?php

namespace App\Services;

use App\Models\StatsFmConnection;
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
        $artist = $track['artists'][0] ?? [];

        $platform = strtolower($item['platform'] ?? 'spotify');
        $source = str_contains($platform, 'apple') ? 'apple_music' : 'spotify';

        // Normalize to a Carbon instance (not a raw string) right here, once.
        // This is the single source of truth for played_at used both when
        // the record is inserted and when PlayRecordSyncer checks for an
        // existing record — keeping them as raw, differently-formatted
        // strings was causing the duplicate-detection query to never match,
        // so repeat syncs re-inserted the same play.
        $playedAt = Carbon::parse($item['endTime'] ?? $item['range']['end'] ?? now());

        return [
            'statsfm_stream_id' => (string) (
                $item['id']
                ?? $item['range']['start']
                ?? $this->derivedStreamId($track['id'] ?? '', $playedAt->toIso8601String(), $platform)
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