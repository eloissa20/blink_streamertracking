<?php

namespace App\Support;

class AllowedArtists
{
    /**
     * Only these artists are synced, stored, or counted anywhere in the
     * app — BLACKPINK plus each of its four members' solo work. Anything
     * else a connected account plays is ignored entirely.
     */
    public const NAMES = [
        'BLACKPINK',
        'JENNIE',
        'ROSÉ',
        'ROSE',
        'LISA',
        'JISOO',
    ];

    public static function isAllowed(?string $artistName): bool
    {
        if (! $artistName) {
            return false;
        }

        return in_array(strtoupper(trim($artistName)), self::NAMES, true);
    }

    /** Lower-cased names, for case-insensitive SQL comparisons. */
    public static function lowerNames(): array
    {
        return array_map('strtolower', self::NAMES);
    }
}
