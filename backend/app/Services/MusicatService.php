<?php

namespace App\Services;

use App\Support\AllowedArtists;
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
     *
     * The "Recently played" list is NOT part of the page's normal flow —
     * it's its own internally-scrolling panel (visible as a distinct thin
     * scrollbar on the rendered page, separate from the browser window's
     * own scrollbar) with a fixed height and `overflow-y: auto`, and it
     * lazy-loads/virtualizes its rows: anything past whatever currently
     * fits in that panel's viewport doesn't exist in the DOM at all until
     * the panel itself is scrolled. A taller *browser window* (previously
     * ->windowSize(1280, 8000)) doesn't change that panel's own height at
     * all, so it never actually triggered more rows to load — confirmed
     * via musicat:debug-parse consistently returning only the 3
     * newest rows no matter how tall the window was. This finds every
     * actually-scrollable element on the page (not just window/body) and
     * scrolls each one directly, which is what the panel's own lazy-load
     * logic is listening for.
     */
    private function renderProfileHtml(string $handle): ?string
    {
        try {
            $shot = Browsershot::url("{$this->profileBaseUrl}/{$handle}")
                ->noSandbox()
                ->waitUntilDOMContentLoaded()
                ->windowSize(1280, 2000)
                ->setDelay($this->renderDelayMs)
                ->timeout(90);

            if ($this->chromePath) {
                $shot->setChromePath($this->chromePath);
            }

            return $shot->evaluate(<<<'JS'
                (async () => {
                    // Any element that actually scrolls internally (own
                    // scrollHeight taller than its own visible height) is
                    // a candidate — the "Recently played" panel is one of
                    // these, not the window. window.scrollTo() alone
                    // never reaches it.
                    const findScrollables = () => Array.from(document.querySelectorAll('*'))
                        .filter((el) => {
                            const style = getComputedStyle(el);
                            const scrollsY = style.overflowY === 'auto' || style.overflowY === 'scroll';
                            return scrollsY && el.scrollHeight > el.clientHeight + 20;
                        });

                    let lastTotalHeight = 0;

                    for (let round = 0; round < 40; round++) {
                        const scrollables = findScrollables();

                        if (scrollables.length === 0) {
                            // No internal panel found (yet, or this page
                            // genuinely doesn't have one) — fall back to
                            // scrolling the window itself as a safety net.
                            window.scrollTo(0, document.body.scrollHeight);
                        } else {
                            for (const el of scrollables) {
                                el.scrollTop = el.scrollHeight;
                                // Some lazy-load implementations listen for
                                // a real scroll event rather than polling
                                // scrollTop, so dispatch one explicitly.
                                el.dispatchEvent(new Event('scroll', { bubbles: true }));
                            }
                        }

                        await new Promise((resolve) => setTimeout(resolve, 600));

                        // Stop once nothing on the page is growing anymore
                        // (every panel has loaded everything it has).
                        const totalHeight = Array.from(document.querySelectorAll('*'))
                            .reduce((sum, el) => sum + el.scrollHeight, 0);

                        if (totalHeight === lastTotalHeight) {
                            break;
                        }
                        lastTotalHeight = totalHeight;
                    }

                    return document.body.innerHTML;
                })()
            JS);
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
     * Visible text nodes AND <img> tags together, in document order, so we
     * can tell which photo immediately preceded which line of text. Used
     * for pulling artist photos out of the "Top artists" section and track
     * artwork out of "Recently played" rows — findUserByHandle() doesn't
     * need images and sticks to the plain textNodes() above.
     *
     * Each entry is ['type' => 'text'|'img', 'value' => string].
     */
    private function textAndImageNodes(Crawler $crawler): array
    {
        $nodes = $crawler->filterXPath(
            '//body//*[not(self::script or self::style)]/text()[normalize-space()] | //body//img'
        )->each(function (Crawler $node) {
            $domNode = $node->getNode(0);

            if ($domNode->nodeName === '#text') {
                $value = trim($domNode->textContent);
                return $value !== '' ? ['type' => 'text', 'value' => $value] : null;
            }

            $src = $domNode->getAttribute('src');

            // Lazy-loading libraries commonly stash the real image URL in
            // a data-* attribute and leave `src` blank (or pointing at a
            // tiny placeholder) until the image scrolls into view. The
            // tall-viewport fix above should trigger the swap for nearly
            // everything, but fall back to these as a safety net for any
            // row that still hasn't swapped by the time we read the DOM.
            if ($src === '' || str_starts_with($src, 'data:')) {
                $src = $domNode->getAttribute('data-src')
                    ?: $domNode->getAttribute('data-lazy-src')
                    ?: '';
            }

            return $src !== '' ? ['type' => 'img', 'value' => $src] : null;
        });

        return array_values(array_filter($nodes));
    }

    /**
     * Same reading-order text lines as textNodes(), plus — for each line —
     * whichever <img> most recently appeared before it (or null). Each
     * image is attributed to exactly the one text line right after it,
     * then "used up", since a "Recently played" row on the page reads
     * [thumbnail img] → track name → artist name → metadata line, and it's
     * only the track name we want the thumbnail attached to.
     *
     * Returns [textLines, imageBeforeLine] — both indexed the same way, so
     * $imageBeforeLine[$i] is the thumbnail (if any) for $textLines[$i].
     */
    private function textNodesWithPrecedingImage(Crawler $crawler): array
    {
        $lines = [];
        $imageBeforeLine = [];
        $lastImgSrc = null;

        foreach ($this->textAndImageNodes($crawler) as $node) {
            if ($node['type'] === 'img') {
                $lastImgSrc = $node['value'];
                continue;
            }

            $lines[] = $node['value'];
            $imageBeforeLine[] = $lastImgSrc;
            $lastImgSrc = null;
        }

        return [$lines, $imageBeforeLine];
    }

    /**
     * Map artist name => photo URL, scraped from the profile's "Top
     * artists" section. This is the only place on the page artist photos
     * actually appear — the "Recently played" rows never show one, which
     * is why normalizeStream() otherwise has nothing to put in
     * artist_image_url. Each card in that section reads, in DOM order:
     * <img>, "#N" (rank), a play count, then the artist name — so the
     * image immediately preceding a name (once rank/count are skipped
     * over) is that artist's photo.
     */
    public function artistImageMap(string $handle): array
    {
        $html = $this->renderProfileHtml($handle);

        if (! $html) {
            return [];
        }

        $crawler = new Crawler($html);
        $nodes = $this->textAndImageNodes($crawler);

        $inSection = false;
        $lastImgSrc = null;
        $map = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'text' && $node['value'] === 'Top artists') {
                $inSection = true;
                continue;
            }

            if (! $inSection) {
                continue;
            }

            // Any other section heading means we've left "Top artists".
            if ($node['type'] === 'text' && in_array($node['value'], ['Top albums', 'Top tracks', 'Favorites', 'Achievements', 'Notes', 'Reviews', 'Mentions'], true)) {
                break;
            }

            if ($node['type'] === 'img') {
                $lastImgSrc = $node['value'];
                continue;
            }

            // Skip rank ("#1") and play-count ("26") lines — the artist
            // name is whichever text line isn't purely one of those, and
            // it's what the most recently seen image belongs to.
            //
            // Play counts aren't always bare digit strings — once an
            // artist has enough plays, Musicat formats the count with a
            // thousands separator ("1,234") or an abbreviated suffix
            // ("12.4K"), and ctype_digit() returns false for both. That
            // let a formatted count fall through as if it were the artist
            // name: it consumed that card's image (map["1,234"] = photo),
            // and the real name right after it found lastImgSrc already
            // used up and got no image at all. That's why higher-played
            // artists (JISOO, LISA) were consistently missing photos while
            // lower-count ones (with a plain unformatted number) were
            // fine — this pattern-matches counts instead of requiring
            // pure digits.
            $isRank = str_starts_with($node['value'], '#');
            $isCount = (bool) preg_match('/^[\d,.]+[KMB]?$/i', $node['value']);

            if (! $isRank && ! $isCount && $lastImgSrc) {
                // Key by the canonical spelling (falling back to the raw
                // scraped text for anyone outside the allow-list) rather
                // than whatever casing this section happened to render —
                // MusicatPlayRecordSyncer looks this map up by the same
                // canonical name it stores plays under, and Musicat
                // doesn't render a member's name identically in every
                // section of the page.
                $key = AllowedArtists::canonicalize($node['value']) ?? $node['value'];
                $map[$key] = $lastImgSrc;
                $lastImgSrc = null; // each photo belongs to exactly one card
            }
        }

        return $map;
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
     * Each row on the page reads, top to bottom: a cover-art thumbnail,
     * track name, artist name, then a metadata line like "Apple Music •
     * 08.07.26 2:49 PM • 5 minutes ago". We scan the page's text nodes in
     * order and, whenever a line matches that metadata pattern, take the
     * two preceding non-empty text lines as [track name, artist name], and
     * whichever image immediately preceded the track name as its artwork.
     */
    public function recentlyPlayed(string $handle, int $limit = 100): array
    {
        $html = $this->renderProfileHtml($handle);

        if (! $html) {
            return [];
        }

        $crawler = new Crawler($html);
        [$lines, $imageBeforeLine] = $this->textNodesWithPrecedingImage($crawler);

        // Deliberately loose: don't require an exact bullet character or a
        // trailing separator. The rendered page's "•" and surrounding
        // spaces may not be the literal U+2022/U+00B7 characters or plain
        // ASCII spaces (e.g. could be non-breaking spaces or a different
        // bullet glyph) — matching "some non-digit stuff" between the
        // source name and the date sidesteps that entirely.
        //
        // The trailing "5 minutes ago" / "2 hours ago" / "just now" clause
        // is captured too (group 4, optional) — see reconcilePlayedAt()
        // below for why: it's the cross-check that keeps the absolute
        // date/time parse from silently drifting by whole-hour amounts.
        $metaPattern = '/^(Apple Music|Spotify)[^\d]{1,8}(\d{2}\.\d{2}\.\d{2})\s+(\d{1,2}:\d{2}\s*[AP]M)(?:[^\w]{1,8}(just now|\d+\s+(?:minute|hour|day)s?\s+ago))?/iu';

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
            $artworkUrl = $i - 2 >= 0 ? ($imageBeforeLine[$i - 2] ?? null) : null;

            if (! $trackName || ! $artistName) {
                continue;
            }

            $playedAt = null;
            try {
                // Musicat renders timestamps in the local time of whatever
                // browser/machine is rendering the page (Asia/Manila here),
                // not UTC — parsing without a source timezone would treat
                // e.g. "2:49 PM" as already-UTC and store every play ~8
                // hours off from the real moment it happened.
                $playedAt = Carbon::createFromFormat('m.d.y g:i A', "{$m[2]} {$m[3]}", 'Asia/Manila')
                    ->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
                continue; // unparsable timestamp — skip rather than guess
            }

            // Cross-check against the row's own "X ago" text, which is
            // immune to the timezone assumption above entirely (it's a
            // plain elapsed duration, not a labeled clock time). If the
            // render machine's actual timezone ever doesn't match the
            // "Asia/Manila" assumed above — a bad deploy config, a Chrome
            // default, whatever — the absolute parse above comes out
            // wrong by a whole number of hours (in the extreme, a full
            // 24h if the mismatch straddles midnight), while this relative
            // figure stays correct. Nudge the absolute value back onto the
            // relative one whenever they disagree by something that looks
            // like exactly that kind of whole-hour offset, rather than
            // ordinary rounding noise (an "X hours ago" bucket is only
            // ever accurate to the nearest hour to begin with).
            if ($m[4] ?? null) {
                $playedAt = $this->reconcilePlayedAt($playedAt, $m[4]);
            }

            // Musicat is the source of truth for when a track was played,
            // but a scraped/parsed value can still come out wrong (e.g. a
            // date-format misread, or a render that straddled midnight).
            // A "recently played" row can never genuinely be from the
            // future, so treat any such result as a parse failure and drop
            // the row rather than surface a bad future-dated entry. A
            // small grace window absorbs ordinary clock skew between this
            // server and whatever machine renders the Musicat page.
            if ($playedAt->isAfter(Carbon::now(config('app.timezone'))->addMinutes(5))) {
                continue;
            }

            $items[] = [
                'track_name' => $trackName,
                'artist_name' => $artistName,
                'artwork_url' => $artworkUrl,
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
     * Source is always `apple_music` — see class docblock. Track id isn't
     * available from the profile page's "Recently played" rows, so that
     * stays null (a stable derived id is used instead — see below);
     * album_name is likewise never shown on this section of the page.
     * artwork_url comes straight from the thumbnail recentlyPlayed()
     * captured next to the track; artist_image_url starts null here since
     * it's not on this section of the page either — it gets filled in
     * afterward by MusicatPlayRecordSyncer from the "Top artists"
     * section's photos instead. Duration is a fixed placeholder since it's
     * never shown on the page (see class docblock for why that means
     * total-listening-time stats are approximate for Musicat-sourced
     * plays).
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
            'artwork_url' => $item['artwork_url'] ?? null,
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

    /**
     * Parse a "5 minutes ago" / "2 hours ago" / "3 days ago" / "just now"
     * string into an absolute instant, anchored to right now in the
     * app's own timezone. Returns null for anything that doesn't match
     * one of those shapes.
     */
    private function parseRelativeAgo(string $text): ?Carbon
    {
        $text = strtolower(trim($text));
        $now = Carbon::now(config('app.timezone'));

        if ($text === 'just now') {
            return $now;
        }

        if (! preg_match('/^(\d+)\s+(minute|hour|day)s?\s+ago$/', $text, $m)) {
            return null;
        }

        $amount = (int) $m[1];

        return match ($m[2]) {
            'minute' => $now->subMinutes($amount),
            'hour' => $now->subHours($amount),
            'day' => $now->subDays($amount),
            default => null,
        };
    }

    /**
     * See the call site in recentlyPlayed() for the "why". Only corrects
     * for drift that looks like a whole-hour (or whole-day) timezone
     * mistake — anything smaller is just the "X hours ago" bucket's own
     * rounding and the more precise absolute value is left as-is.
     */
    private function reconcilePlayedAt(Carbon $absolute, string $relativeAgoText): Carbon
    {
        $relativeEstimate = $this->parseRelativeAgo($relativeAgoText);

        if (! $relativeEstimate) {
            return $absolute;
        }

        $diffMinutes = ($absolute->getTimestamp() - $relativeEstimate->getTimestamp()) / 60;
        $nearestHourMultiple = (int) round($diffMinutes / 60) * 60;

        if ($nearestHourMultiple !== 0 && abs($diffMinutes - $nearestHourMultiple) <= 5) {
            return $absolute->copy()->subMinutes($nearestHourMultiple);
        }

        return $absolute;
    }

    private function derivedStreamId(string $trackName, string $artistName, string $playedAt): string
    {
        return 'musicat_derived_' . md5(strtolower($trackName) . '|' . strtolower($artistName) . '|' . $playedAt);
    }
}