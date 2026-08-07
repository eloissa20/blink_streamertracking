<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads Apple Music listening data off a public Musicat profile
 * (https://musicat.fm/<handle>) by rendering it with a real headless
 * browser and reading the resulting DOM.
 *
 * WHY A SCRAPER, NOT AN API CLIENT: Musicat has no public developer API —
 * confirmed by requesting a profile URL directly, which returns only an
 * empty single-page-app shell ("You need to enable JavaScript to run this
 * app"). There's no server-rendered HTML and no discoverable JSON endpoint
 * to call instead. The only way to read a profile's data at all is to run
 * their JavaScript the way a browser does, then read what it renders —
 * which is what this class does via spatie/browsershot (Node + Puppeteer/
 * Chromium under the hood).
 *
 * THIS IS INHERENTLY FRAGILE. It depends on:
 *  - The profile being public (same requirement as Stats.fm).
 *  - Musicat's page layout/text staying roughly as observed. This was
 *    built by reading two screenshots of a rendered profile page, not
 *    Musicat's actual markup/class names — which weren't inspectable from
 *    here. Extraction below deliberately avoids guessing CSS class names
 *    (those are almost certainly hashed/generated and will be wrong) and
 *    instead walks the page's visible text in reading order, pattern
 *    matching on the "<Source> • <date> <time> • <relative time>" line
 *    under each "Recently played" row. If Musicat changes their layout,
 *    or if the real DOM order differs from the visual order assumed here,
 *    this will need re-tuning against the real rendered HTML.
 *  - A working headless Chromium on the server running this (see
 *    config/musicat.php — MUSICAT_CHROME_PATH).
 *  - Musicat's own terms of service permitting this kind of access to a
 *    public profile page. Worth confirming before running this in
 *    production.
 *  - Duration per play isn't visible anywhere on the profile page, so
 *    unlike Stats.fm (which gives an exact duration_ms per stream),
 *    Musicat-sourced plays get a fixed placeholder duration (see
 *    normalizeStream()) — meaning total-listening-time stats for Apple
 *    Music will be an estimate, not the real figure.
 */
class MusicatService
{
    private string $profileBaseUrl;
    private ?string $chromePath;
    private int $renderDelayMs;

    public function __construct()
    {
        $this->profileBaseUrl = rtrim(config('musicat.profile_base_url'), '/');
        $this->chromePath = config('musicat.chrome_path');
        $this->renderDelayMs = config('musicat.render_delay_ms');
    }

    /**
     * Render a Musicat profile page and return its fully-rendered body
     * HTML, or null if the page couldn't be loaded/rendered at all.
     */
    private function renderProfileHtml(string $handle): ?string
    {
        try {
            $shot = Browsershot::url("{$this->profileBaseUrl}/{$handle}")
                ->noSandbox()
                ->waitUntilNetworkIdle()
                ->setDelay($this->renderDelayMs)
                ->timeout(30);

            if ($this->chromePath) {
                $shot->setChromePath($this->chromePath);
            }

            return $shot->bodyHtml();
        } catch (\Throwable $e) {
            Log::warning('Musicat profile render failed', [
                'handle' => $handle,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * All non-empty, trimmed visible text nodes in the page, in DOM
     * (reading) order. This is the basis for every extraction below,
     * since it's the one thing we can rely on regardless of what tags/
     * classes Musicat actually renders.
     */
    private function textNodes(Crawler $crawler): array
    {
        return $crawler->filterXPath('//body//*[not(self::script or self::style)]/text()[normalize-space()]')
            ->each(fn (Crawler $node) => trim($node->text()));
    }

    /**
     * Look up a public Musicat profile by handle (the part after
     * musicat.fm/ in a profile URL, e.g. "shamara" for musicat.fm/shamara).
     * Used when a user connects their account.
     */
    public function findUserByHandle(string $handle): ?array
    {
        $html = $this->renderProfileHtml($handle);

        if (! $html) {
            return null;
        }

        $crawler = new Crawler($html);
        $lines = $this->textNodes($crawler);

        // The profile panel shows a display-name heading directly above an
        // "@handle" line (see the right-hand column in a musicat.fm
        // profile). We look for the "@handle" text and take the line
        // immediately before it as the display name.
        $atHandleIndex = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '@')) {
                $atHandleIndex = $i;
                break;
            }
        }

        if ($atHandleIndex === null) {
            // Couldn't find anything resembling a profile — likely not
            // public, doesn't exist, or the page didn't finish rendering.
            return null;
        }

        $displayName = $atHandleIndex > 0 ? $lines[$atHandleIndex - 1] : null;
        $handleFromPage = ltrim($lines[$atHandleIndex], '@');

        return [
            'id' => $handleFromPage,
            'username' => $handleFromPage,
            'displayName' => $displayName,
            'avatarUrl' => null, // not reliably extractable from text nodes
        ];
    }

    /**
     * Scrape the "Recently played" rows off a profile page. Returns raw
     * items shaped for normalizeStream() — normalized/persisted by
     * MusicatPlayRecordSyncer.
     *
     * Each row on the page reads, top to bottom: track name, artist name,
     * then a metadata line like "Apple Music • 08.07.26 2:49 PM • 5 minutes
     * ago". We scan the page's text nodes in order and, whenever a line
     * matches that metadata pattern, take the two preceding non-empty text
     * lines as [track name, artist name].
     */
    public function recentlyPlayed(string $handle, int $limit = 100): array
    {
        $html = $this->renderProfileHtml($handle);

        if (! $html) {
            return [];
        }

        $crawler = new Crawler($html);
        $lines = $this->textNodes($crawler);

        $metaPattern = '/^(Apple Music|Spotify)\s*[•·]\s*(\d{2}\.\d{2}\.\d{2})\s+(\d{1,2}:\d{2}\s*[AP]M)\s*[•·]/i';

        $items = [];
        foreach ($lines as $i => $line) {
            if (! preg_match($metaPattern, $line, $m)) {
                continue;
            }

            $source = strtolower($m[1]) === 'apple music' ? 'apple_music' : 'spotify';

            // Only Apple Music rows matter — a Musicat connection is the
            // Apple Music source exclusively, even though Musicat itself
            // also shows Spotify plays on the same profile.
            if ($source !== 'apple_music') {
                continue;
            }

            $artistName = $i - 1 >= 0 ? $lines[$i - 1] : null;
            $trackName = $i - 2 >= 0 ? $lines[$i - 2] : null;

            if (! $trackName || ! $artistName) {
                continue;
            }

            $playedAt = null;
            try {
                $playedAt = Carbon::createFromFormat('m.d.y g:i A', "{$m[2]} {$m[3]}");
            } catch (\Throwable) {
                continue; // unparsable timestamp — skip rather than guess
            }

            $items[] = [
                'track_name' => $trackName,
                'artist_name' => $artistName,
                'played_at' => $playedAt,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Normalize a raw scraped item into the shape PlayRecord expects.
     * Source is always `apple_music` — see class docblock. Track/artist
     * ids, album, and artwork aren't available from the profile page's
     * "Recently played" rows, so those fields are left null; duration is
     * a fixed placeholder since it's never shown on the page (see class
     * docblock for why that means total-listening-time stats are
     * approximate for Musicat-sourced plays).
     */
    public function normalizeStream(array $item): array
    {
        $playedAt = $item['played_at'];

        return [
            'musicat_stream_id' => $this->derivedStreamId($item['track_name'], $item['artist_name'], $playedAt->toIso8601String()),
            'track_id' => $this->derivedStreamId($item['track_name'], $item['artist_name'], ''), // stable per track+artist, not per play
            'track_name' => $item['track_name'],
            'artist_id' => strtolower(trim($item['artist_name'])),
            'artist_name' => $item['artist_name'],
            'album_name' => null,
            'artwork_url' => null,
            'artist_image_url' => null,
            'source' => 'apple_music',
            // Placeholder: the profile page never shows a play's duration.
            // Set just above StreamRules::MIN_STREAM_DURATION_MS so every
            // scraped row counts as a stream (Musicat already only lists
            // completed plays), but this is NOT a real duration.
            'duration_ms' => 31_000,
            'played_at' => $playedAt,
        ];
    }

    private function derivedStreamId(string $trackName, string $artistName, string $playedAt): string
    {
        return 'musicat_derived_' . md5(strtolower($trackName) . '|' . strtolower($artistName) . '|' . $playedAt);
    }
}
