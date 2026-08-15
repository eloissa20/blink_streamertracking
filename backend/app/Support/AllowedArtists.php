<?php

namespace App\Support;

class AllowedArtists
{
    /**
     * Only these artists are synced, stored, or counted anywhere in the
     * app — BLACKPINK plus each of its four members' solo work. Anything
     * else a connected account plays is ignored entirely (or, for
     * Stats.fm-sourced rows, kept only in "recently played" — see
     * PlayRecordSyncer).
     *
     * One canonical spelling per artist. Rosé is deliberately listed only
     * as "ROSÉ" — see ALIASES below for how "ROSE" (no accent) still
     * matches her.
     */
    public const NAMES = [
        'BLACKPINK',
        'JENNIE',
        'ROSÉ',
        'LISA',
        'JISOO',
    ];

    /**
     * Alternate spellings/stylizations a source might send that should
     * still resolve to one of the canonical NAMES above, keyed by their
     * upper-cased form. Currently just the accent-less "ROSE" → "ROSÉ".
     */
    private const ALIASES = [
        'ROSE' => 'ROSÉ',
    ];

    /**
     * Whether $artistName refers to one of our allow-listed artists,
     * regardless of case. See canonicalize() for why this must stay
     * case-insensitive.
     */
    public static function isAllowed(?string $artistName): bool
    {
        return self::canonicalize($artistName) !== null;
    }

    /**
     * Normalize to a single Unicode composition form (NFC) before any
     * comparison. "É" can be encoded two different-but-visually-identical
     * ways: as one precomposed codepoint (U+00C9), or as a plain "E"
     * (U+0045) followed by a separate combining acute accent (U+0301).
     * Both render as "É" and both survive mb_strtoupper() unchanged, but
     * they are different byte sequences and a strict `===` comparison
     * between one of each will never match — no amount of case-folding
     * fixes that, since it isn't a casing difference.
     *
     * Sources aren't consistent about which form they send: Stats.fm/
     * Spotify's API has been observed sending "Rosé" in decomposed form
     * for some streams, which fails to match our precomposed "ROSÉ" in
     * NAMES below even after mb_strtoupper() folds the case — the exact
     * "APT. attributed to Bruno Mars instead of ROSÉ" bug. Folding every
     * comparison through this first closes that gap regardless of which
     * form either side happens to be in.
     *
     * Falls back to the original string if the intl extension (which
     * provides Normalizer) isn't loaded, rather than fatal-erroring —
     * comparisons degrade to the old form-sensitive behavior in that case
     * instead of breaking entirely.
     */
    private static function normalizeForm(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            return \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        return $value;
    }

    /**
     * Case-insensitively match $artistName against the allow-list and
     * return the one canonical spelling it should be stored/displayed
     * under (e.g. "LiSA", "Lisa", "LISA" all return "LISA"), or null if
     * it isn't one of ours.
     *
     * This MUST be case-insensitive, even though an earlier version of
     * this file argued the opposite. That argument assumed BLACKPINK's
     * Lisa is always credited as all-caps "LISA" everywhere, and that a
     * plain uppercase/lowercase fold would therefore only ever pull in
     * the unrelated Japanese singer "LiSA" (Risa Oribe). In practice,
     * Stats.fm and the Musicat scrape do NOT consistently send BLACKPINK
     * Lisa's own tracks back under one casing — some of her own solo
     * releases come back as "LiSA", not "LISA". Comparing case-
     * sensitively against a hardcoded casing silently drops whichever of
     * her real plays don't happen to match it, which is exactly the "LISA
     * missing from Top Tracks/Top Artists" bug. It also affected every
     * other member on the Musicat/Apple Music side, since
     * MusicatPlayRecordSyncer gates ALL of its inserts on this same
     * check — any member whose scraped name casing didn't exactly match
     * the constant (e.g. "Jisoo" vs "JISOO") had her Apple Music plays
     * silently dropped, not just missing a photo.
     *
     * Folding case does mean a source that also plays the real Japanese
     * singer LiSA would have her plays merged in here too. That's a real
     * trade-off, but the alternative — case-sensitive matching — doesn't
     * actually protect against that in this app's data anyway (see
     * above) while guaranteeing BLACKPINK Lisa's own plays are
     * incomplete. If a reliable per-source artist id ever becomes
     * available from every source (Stats.fm already provides one from
     * Spotify; Musicat's scrape does not), prefer disambiguating on that
     * instead of name casing.
     */
    public static function canonicalize(?string $artistName): ?string
    {
        if (! $artistName) {
            return null;
        }

        // mb_strtoupper(), not strtoupper(): plain strtoupper() only
        // uppercases ASCII a-z and leaves accented characters untouched.
        // A source that sends "Rosé" (lowercase é) would uppercase to
        // "ROSé" under strtoupper() — a different byte sequence from the
        // canonical "ROSÉ" below — and silently fail to match, causing
        // her plays to fall through to whichever other artist is listed
        // first on the track (see StatsFmService::attributedArtist()).
        //
        // normalizeForm() runs FIRST, before case-folding: see its
        // docblock for why a precomposed/decomposed mismatch survives
        // mb_strtoupper() untouched and needs its own fix.
        $needle = mb_strtoupper(self::normalizeForm(trim($artistName)), 'UTF-8');
        $needle = self::ALIASES[$needle] ?? $needle;

        foreach (self::NAMES as $name) {
            if (mb_strtoupper(self::normalizeForm($name), 'UTF-8') === $needle) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Every upper-cased spelling (canonical names plus aliases) that
     * should match, for callers that need a flat list to build a SQL
     * IN (...) clause against instead of calling canonicalize() per row
     * (e.g. PlayRecord::scopeAllowedArtists, as a defensive read-time
     * net for any pre-existing rows stored before a sync started
     * canonicalizing artist_name).
     */
    public static function matchableUpperNames(): array
    {
        return array_values(array_unique(array_merge(
            array_map(fn ($name) => mb_strtoupper($name, 'UTF-8'), self::NAMES),
            array_keys(self::ALIASES)
        )));
    }
}