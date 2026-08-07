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

        $needle = strtoupper(trim($artistName));
        $needle = self::ALIASES[$needle] ?? $needle;

        foreach (self::NAMES as $name) {
            if (strtoupper($name) === $needle) {
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
            array_map('strtoupper', self::NAMES),
            array_keys(self::ALIASES)
        )));
    }
}
