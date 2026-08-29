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
     *
     * IMPORTANT — virtualization also EVICTS rows, not just defers them.
     * As the panel is scrolled further to load older rows, whatever rows
     * were rendered earlier (including the newest ones, right after
     * whatever we already saved on a previous sync) can get unmounted
     * from the DOM once they're far enough outside the panel's current
     * viewport. Grabbing document.body.innerHTML only once, after
     * scrolling settles, therefore only reflects whatever rows happen to
     * still be mounted at that final scroll position — not everything
     * that was ever rendered along the way. That's what produced the
     * missing-chunk-in-the-middle bug: a whole stretch of history would
     * flash into the DOM mid-scroll and then get evicted again before the
     * snapshot was taken, so it was scraped by nobody, ever.
     *
     * The fix is to snapshot the panel's rows at EVERY scroll round (not
     * just the end) and accumulate them into a de-duplicated, order-
     * preserving set, then splice that full accumulated set back into the
     * panel before reading out the final HTML. That way a row only has to
     * be mounted for one round, at any point during scrolling, to survive
     * into what we return — eviction afterwards no longer loses it.
     *
     * A SECOND bug hid behind that first fix and produced the exact same
     * symptom (a chunk of history missing from the middle of the
     * timeline): each round set `el.scrollTop = el.scrollHeight`, which
     * jumps the panel from wherever it is straight to the absolute
     * bottom in one step. A virtualized panel only ever mounts rows for
     * whatever scroll position it's *currently* at — jumping straight
     * from top to bottom means every position in between (i.e. most of
     * the list) is never actually visited, so those rows never mount at
     * all and snapshotRows() never sees them, no matter how many rounds
     * run. Only the top-of-list rows (captured before the first scroll)
     * and the bottom-of-list rows (captured after the jump) ever made it
     * into the accumulated set — everything else fell straight through.
     * The stop condition compounded this: it summed scrollHeight across
     * the whole page, but a virtualized list's scroll container commonly
     * has a fixed "sizer" height computed once from the total item count
     * rather than from how much has actually been rendered, so that sum
     * doesn't change between rounds — the loop concluded "nothing is
     * growing anymore" and bailed out almost immediately, often before
     * even the bottom-of-list jump's rows had time to mount and be
     * snapshotted.
     *
     * Fixed by advancing each panel's scrollTop incrementally (roughly
     * one viewport's worth per round, capped at the panel's max) instead
     * of jumping straight to the end, so every intermediate scroll
     * position — and therefore every row that ever mounts along the way
     * — gets a chance to be snapshotted. The stop condition now tracks
     * each panel's own scrollTop reaching its own max, requiring several
     * consecutive rounds with no panel able to move further (rather than
     * a single potentially-misleading page-height reading) before
     * concluding the whole list has actually been walked.
     */
    private function renderProfileHtml(string $handle): ?string
    {
        try {
            $shot = Browsershot::url("{$this->profileBaseUrl}/{$handle}")
                ->noSandbox()
                ->waitUntilDOMContentLoaded()
                ->windowSize(1280, 2000)
                ->setDelay($this->renderDelayMs)
                // The incremental scroll-and-snapshot loop below can now
                // run up to 150 rounds (~75s worst case) to walk a long
                // history one viewport-step at a time — bumped up from 90s
                // so a large backlog doesn't get cut off mid-scroll by the
                // page-render timeout itself.
                ->timeout(150);

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

                    // Virtualized lists commonly wrap every row in one
                    // inner "sizer" div (position: relative, huge height)
                    // that itself sits inside the scrollable panel — drill
                    // into that wrapper so we snapshot the actual row
                    // elements, not the single div that contains all of
                    // them.
                    const rowContainer = (panel) => {
                        let container = panel;
                        while (
                            container.children.length === 1 &&
                            container.children[0].children.length > 1
                        ) {
                            container = container.children[0];
                        }
                        return container;
                    };

                    // panel element -> Map(rowTextContent -> rowOuterHTML),
                    // built up across every round so rows that later get
                    // evicted by virtualization are still remembered.
                    const seenRowsByPanel = new Map();

                    const snapshotRows = () => {
                        for (const panel of findScrollables()) {
                            if (!seenRowsByPanel.has(panel)) {
                                seenRowsByPanel.set(panel, new Map());
                            }
                            const seen = seenRowsByPanel.get(panel);

                            for (const row of Array.from(rowContainer(panel).children)) {
                                const key = row.textContent.trim();
                                if (key && !seen.has(key)) {
                                    seen.set(key, row.outerHTML);
                                }
                            }
                        }
                    };

                    // A panel counts as "at its floor" once it can't be
                    // scrolled any further — small fudge factor for
                    // sub-pixel layout rounding.
                    const atBottom = (el) => el.scrollTop + el.clientHeight >= el.scrollHeight - 2;

                    // Require several consecutive rounds where NOTHING
                    // moved (every panel already at its own bottom, and
                    // the window fallback already maxed) before
                    // concluding the whole list has genuinely been
                    // walked. A single quiet round isn't enough proof —
                    // e.g. a panel that only just reached bottom still
                    // needs one more round for its final rows to mount
                    // and get snapshotted.
                    const REQUIRED_STABLE_ROUNDS = 3;
                    let stableRounds = 0;

                    for (let round = 0; round < 150; round++) {
                        // Snapshot BEFORE scrolling further, so this
                        // round's currently-mounted rows are captured
                        // before anything has a chance to be evicted.
                        snapshotRows();

                        const scrollables = findScrollables();
                        let movedThisRound = false;

                        if (scrollables.length === 0) {
                            // No internal panel found (yet, or this page
                            // genuinely doesn't have one) — fall back to
                            // scrolling the window itself as a safety net.
                            const before = window.scrollY;
                            window.scrollTo(0, document.body.scrollHeight);
                            if (window.scrollY !== before) movedThisRound = true;
                        } else {
                            for (const el of scrollables) {
                                if (atBottom(el)) continue;

                                // Advance roughly one viewport's worth per
                                // round rather than jumping straight to the
                                // very bottom — a virtualized panel only
                                // mounts rows for whatever position it's
                                // AT, so every intermediate stop needs to
                                // actually happen for its rows to ever be
                                // seen. Capped at the panel's own max so
                                // this never overshoots past the bottom.
                                const step = Math.max(el.clientHeight * 0.8, 100);
                                el.scrollTop = Math.min(el.scrollTop + step, el.scrollHeight);
                                // Some lazy-load implementations listen for
                                // a real scroll event rather than polling
                                // scrollTop, so dispatch one explicitly.
                                el.dispatchEvent(new Event('scroll', { bubbles: true }));
                                movedThisRound = true;
                            }
                        }

                        await new Promise((resolve) => setTimeout(resolve, 500));

                        if (movedThisRound) {
                            stableRounds = 0;
                            continue;
                        }

                        stableRounds++;
                        if (stableRounds >= REQUIRED_STABLE_ROUNDS) {
                            // One last catch-up snapshot of wherever
                            // scrolling finally settled, then stop.
                            snapshotRows();
                            break;
                        }
                    }

                    // Replace each virtualized panel's rows with every row
                    // ever observed across all rounds (first-seen order,
                    // which for a newest-first list stays newest-to-oldest)
                    // so nothing that scrolled through and got evicted is
                    // lost from what we return.
                    for (const [panel, seen] of seenRowsByPanel.entries()) {
                        rowContainer(panel).innerHTML = Array.from(seen.values()).join('');
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
     * True for a Musicat internal platform id (a UUID, as seen in
     * musicat.fm/users/<uuid>/history links) as opposed to a public handle
     * (e.g. "shamara"). Used to decide whether recentlyPlayed() can target
     * the dedicated History page (see renderHistoryHtml()) or has to fall
     * back to scraping the profile page's small "Recently played" panel —
     * connections created before this id started being captured only have
     * the handle on file, so both paths need to keep working.
     */
    private function isMusicatUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($value));
    }

    /**
     * href attributes of every <a> tag in the page, in document order.
     * Only used to pull the internal user id out of the profile page's own
     * link to its "History" tab (see findUserByHandle()) — everything else
     * in this class deliberately sticks to visible text, but that id isn't
     * rendered as text anywhere, only embedded in that one link's URL.
     */
    private function anchorHrefs(Crawler $crawler): array
    {
        return $crawler->filterXPath('//a[@href]')->each(fn (Crawler $a) => $a->attr('href'));
    }

    /**
     * Render the dedicated Musicat "History" page
     * (musicat.fm/users/<uuid>/history) and return its body HTML.
     *
     * This is a DIFFERENT page from the profile (musicat.fm/<handle>) that
     * renderProfileHtml() reads — confirmed by inspecting the app's own
     * navigation: the profile page has a small "Recently played" panel
     * that's an internally-virtualizing preview, while a separate
     * "History" tab/page lists a given day's plays in full, with a day
     * selector across the current (and, on paid plans, older) dates. This
     * is the actual complete record — it's what Musicat's own per-day play
     * count on that page reflects — and scraping it directly, rather than
     * fighting the profile panel's virtualization, is what actually closes
     * the "only 3 songs" gap end to end.
     *
     * Row detection here does NOT reuse renderProfileHtml()'s "sizer div /
     * rowContainer" approach, because that assumes rows live inside one
     * particular internally-scrolling wrapper — true for the profile
     * panel, but not verified for this page, which may render its list in
     * normal page flow instead (no distinct inner scrollbar was visible in
     * the observed screenshot). Instead, rows are found directly by
     * content: any leaf element whose text matches the same "<Source> •
     * <date> <time>" pattern used for final parsing is treated as a row's
     * metadata line, and a fixed number of ancestor levels are walked up
     * to capture that row's wrapper (thumbnail + track/artist text sitting
     * alongside it). This works whether the list turns out to be
     * virtualized inside a panel (the incremental scroll below gives each
     * position a chance to mount and be snapshotted, same fix as the
     * profile panel) or already fully present in the DOM (the very first,
     * pre-scroll snapshot already captures everything and the scroll loop
     * below is then just a harmless no-op).
     *
     * Also makes a best-effort attempt to click through any other visible
     * day-selector tabs (stepping backward from today) so a gap spanning
     * more than one calendar day still gets fully covered, not just
     * today's plays. This part is deliberately conservative: Musicat's
     * exact tab markup hasn't been inspected directly, only inferred from
     * a screenshot, so it's wrapped so that if the tab-clicking guess is
     * wrong (or those days are paywalled on a free plan), it simply stops
     * and keeps whatever today's data already captured rather than
     * corrupting or blanking out the scrape.
     */
    private function renderHistoryHtml(string $historyUserId): ?string
    {
        try {
            $shot = Browsershot::url("{$this->profileBaseUrl}/users/{$historyUserId}/history")
                ->noSandbox()
                ->waitUntilDOMContentLoaded()
                ->windowSize(1280, 2000)
                ->setDelay($this->renderDelayMs)
                ->timeout(150);

            if ($this->chromePath) {
                $shot->setChromePath($this->chromePath);
            }

            return $shot->evaluate(<<<'JS'
                (async () => {
                    const ROW_META_RE = /(Apple Music|Spotify)[^\d]{1,8}\d{2}\.\d{2}\.\d{2}\s+\d{1,2}:\d{2}\s*[AP]M/i;

                    const findScrollables = () => Array.from(document.querySelectorAll('*'))
                        .filter((el) => {
                            const style = getComputedStyle(el);
                            const scrollsY = style.overflowY === 'auto' || style.overflowY === 'scroll';
                            return scrollsY && el.scrollHeight > el.clientHeight + 20;
                        });

                    // Find each row by its own content rather than by a
                    // structural guess about a shared wrapper — see the
                    // docblock above for why. Walk up a handful of
                    // ancestor levels from the metadata-line element until
                    // hitting something wider than a single text node,
                    // which should be the row's own container (artwork +
                    // text block together).
                    //
                    // IMPORTANT: "wider than a single text node" was
                    // previously read as "the first ancestor with 2+
                    // children" and the climb stopped there immediately.
                    // On the real markup that first multi-child ancestor
                    // is the text-only wrapper (track name / artist name /
                    // meta line) — the thumbnail <img> is a *sibling* of
                    // that wrapper, one level further up, so stopping here
                    // captured a row with no image in it at all, and every
                    // track/artist fell back to the letter-avatar
                    // placeholder client-side. Instead, keep climbing
                    // (still capped at 6 levels) until the candidate
                    // actually contains an <img> descendant, so the
                    // captured row includes the thumbnail. If no <img>
                    // ever shows up within the cap (e.g. a row genuinely
                    // has no artwork), fall back to the old first-multi-
                    // child behavior rather than climbing all the way to
                    // some much larger ancestor.
                    const findRowElements = () => Array.from(document.querySelectorAll('*'))
                        .filter((el) => el.children.length === 0 && ROW_META_RE.test(el.textContent || ''))
                        .map((metaEl) => {
                            let row = metaEl;
                            let fallback = null;
                            for (let i = 0; i < 6 && row.parentElement; i++) {
                                row = row.parentElement;
                                if (!fallback && row.children.length >= 2) {
                                    fallback = row;
                                }
                                if (row.querySelector('img')) {
                                    return row;
                                }
                            }
                            return fallback || row;
                        });

                    // textContent -> outerHTML, de-duplicated, first-seen
                    // order preserved, so a row only needs to be mounted
                    // for one snapshot (at any scroll position, on any day
                    // visited) to survive into the final result.
                    const seenRows = new Map();

                    const snapshotRows = () => {
                        for (const row of findRowElements()) {
                            const key = row.textContent.trim();
                            if (key && !seenRows.has(key)) {
                                seenRows.set(key, row.outerHTML);
                            }
                        }
                    };

                    const atBottom = (el) => el.scrollTop + el.clientHeight >= el.scrollHeight - 2;

                    // Incrementally scroll (whatever panel or the window
                    // itself) and snapshot at every step until nothing
                    // moves for a few consecutive rounds — same fix as
                    // renderProfileHtml(): jumping straight to the bottom
                    // in one step would skip every row that only ever
                    // mounts at an intermediate scroll position.
                    const walkAndSnapshot = async () => {
                        const REQUIRED_STABLE_ROUNDS = 3;
                        let stableRounds = 0;

                        for (let round = 0; round < 80; round++) {
                            snapshotRows();

                            const scrollables = findScrollables();
                            let movedThisRound = false;

                            if (scrollables.length === 0) {
                                const before = window.scrollY;
                                const target = Math.min(window.scrollY + window.innerHeight * 0.8, document.body.scrollHeight);
                                window.scrollTo(0, target);
                                if (window.scrollY !== before) movedThisRound = true;
                            } else {
                                for (const el of scrollables) {
                                    if (atBottom(el)) continue;
                                    const step = Math.max(el.clientHeight * 0.8, 100);
                                    el.scrollTop = Math.min(el.scrollTop + step, el.scrollHeight);
                                    el.dispatchEvent(new Event('scroll', { bubbles: true }));
                                    movedThisRound = true;
                                }
                            }

                            await new Promise((resolve) => setTimeout(resolve, 500));

                            if (movedThisRound) {
                                stableRounds = 0;
                                continue;
                            }

                            stableRounds++;
                            if (stableRounds >= REQUIRED_STABLE_ROUNDS) {
                                snapshotRows();
                                break;
                            }
                        }
                    };

                    // Today's tab is selected by default on page load —
                    // walk and snapshot it first.
                    await walkAndSnapshot();

                    // Best-effort: also walk backward through any other
                    // day tabs so a gap spanning more than one calendar
                    // day still gets covered. See docblock — deliberately
                    // defensive, degrades to "just today's data" on any
                    // mismatch with the real markup.
                    try {
                        const dayTabs = Array.from(document.querySelectorAll('button, [role="button"], a'))
                            .filter((el) => /^(sun|mon|tue|wed|thu|fri|sat)\s+\d{1,2}$/i.test((el.textContent || '').trim()));

                        // Reading order is oldest-to-newest (matching the
                        // screenshot); walk newest-to-oldest, skipping
                        // today's (already captured) tab.
                        const orderedTabs = dayTabs.slice().reverse();

                        for (const tab of orderedTabs) {
                            const before = seenRows.size;

                            tab.click();
                            await new Promise((resolve) => setTimeout(resolve, 700));
                            await walkAndSnapshot();

                            if (seenRows.size === before) {
                                // Nothing new showed up for this day —
                                // genuinely empty, locked behind a paid
                                // plan, or this wasn't really a day tab.
                                // Stop rather than keep clicking blind.
                                break;
                            }
                        }
                    } catch (e) {
                        // Swallow — today's data above is still valid.
                    }

                    // Splice every row ever seen, across every day
                    // visited, into one flat container so the PHP-side
                    // parser (which just scans text nodes in order) sees
                    // the complete set regardless of which page/day each
                    // row actually came from.
                    const combined = document.createElement('div');
                    combined.innerHTML = Array.from(seenRows.values()).join('');
                    document.body.innerHTML = '';
                    document.body.appendChild(combined);

                    return document.body.innerHTML;
                })()
            JS);
        } catch (\Throwable $e) {
            Log::warning('Musicat history render failed', [
                'historyUserId' => $historyUserId,
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

        // The profile page links to this same account's "History" tab
        // (musicat.fm/users/<uuid>/history) — that link is the only place
        // the account's real internal id shows up anywhere; it's never
        // rendered as visible text. Capturing it here lets recentlyPlayed()
        // target the full History page instead of the profile's small
        // "Recently played" panel. Not fatal if it's missing (e.g. a
        // profile page layout that doesn't expose that link) — recentlyPlayed()
        // falls back to the old profile-panel scrape in that case.
        $historyUserId = null;
        foreach ($this->anchorHrefs($crawler) as $href) {
            if ($href && preg_match('#/users/([0-9a-f-]{20,40})(?:/|$)#i', $href, $m)) {
                $historyUserId = $m[1];
                break;
            }
        }

        return [
            'id' => $handleFromPage,
            'username' => $handleFromPage,
            'displayName' => $displayName,
            'avatarUrl' => null, // not reliably extractable from text nodes
            'historyUserId' => $historyUserId,
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
     *
     * $limit is a safety ceiling on parsing/storage cost, not a
     * "how far back Musicat lets us look" cap — the underlying render
     * accumulates every row seen across the whole scroll (and, on the
     * History page, across every day tab walked — see renderHistoryHtml()),
     * so raising this simply gives MusicatPlayRecordSyncer more room to
     * fill in a large gap (e.g. after a missed sync run) in one pass,
     * rather than only ever returning the newest handful.
     *
     * $musicatUserId should be the account's internal Musicat id (a UUID,
     * as captured by findUserByHandle()'s 'historyUserId') — when it looks
     * like one, this targets the dedicated History page directly, which is
     * the complete record and isn't limited to whatever fits in the
     * profile's small "Recently played" panel (see renderHistoryHtml()'s
     * docblock for why that panel alone can't be made to yield everything,
     * even with the scroll-accumulation fix applied to it). $handle (the
     * public profile handle, e.g. "shamara") is required in that case to
     * fall back to the old profile-panel scrape for connections made
     * before the history id started being captured.
     */
    public function recentlyPlayed(string $musicatUserId, ?string $handle = null, int $limit = 300): array
    {
        $html = $this->isMusicatUuid($musicatUserId)
            ? $this->renderHistoryHtml($musicatUserId)
            : $this->renderProfileHtml($handle ?? $musicatUserId);

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

            // Musicat's row credits every artist on the track as one line,
            // e.g. "LISA, Tyla" or "JISOO, ZAYN" — not just the allow-listed
            // member. Resolve that down to whichever credited artist this
            // play should be attributed to before it goes any further. See
            // attributedArtistName() for why this matters: without it, a
            // collab where the allowed member isn't credited first (or is
            // one of several names on the line) never matches
            // AllowedArtists::isAllowed()'s exact comparison downstream in
            // MusicatPlayRecordSyncer, and the whole play is silently
            // dropped — not stored anywhere, not just missing a photo.
            $artistName = $this->attributedArtistName($artistName);

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
     * Pick which credited artist a Musicat "Recently played" row should be
     * attributed to. The row's artist line is plain rendered text, not a
     * structured list — a collab shows up as a single string like "LISA,
     * Tyla", "JISOO, ZAYN", or "ROSÉ, Bruno Mars", with the allow-listed
     * member not always credited first (e.g. "Ella Jay, LISA").
     *
     * Mirrors StatsFmService::attributedArtist() (same bug, same fix, just
     * a delimited string here instead of Spotify's structured `artists`
     * array): split on the punctuation/words a credit line uses to join
     * multiple artists, and prefer whichever piece is actually on the
     * allow-list. Falls back to the first-credited (primary) artist when
     * none of the pieces are allowed — that play gets filtered out
     * downstream regardless, so which non-allowed name it's nominally
     * attributed to doesn't matter.
     *
     * A line with no separator at all (the common case — just "JENNIE")
     * splits into one piece and returns unchanged.
     */
    private function attributedArtistName(string $rawArtistLine): string
    {
        $pieces = preg_split(
            '/\s*(?:,|&|\bfeat\.?\b|\bfeaturing\b|\bft\.?\b|\bwith\b|\bx\b|\band\b)\s*/i',
            $rawArtistLine,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $pieces = array_values(array_filter(array_map('trim', $pieces), fn ($p) => $p !== ''));

        if (empty($pieces)) {
            return $rawArtistLine;
        }

        foreach ($pieces as $candidate) {
            if (AllowedArtists::isAllowed($candidate)) {
                return $candidate;
            }
        }

        return $pieces[0];
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