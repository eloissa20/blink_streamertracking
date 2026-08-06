<?php

namespace App\Support;

class StreamRules
{
    /**
     * Minimum listened duration, in milliseconds, for a play to count as
     * "a stream" — 30 seconds, matching the threshold both Spotify and
     * Apple Music themselves use for what counts as a play. Applied the
     * exact same way regardless of source, so a quick skip on Spotify and
     * a quick skip on Apple Music are treated identically instead of the
     * two platforms silently using different rules.
     */
    public const MIN_STREAM_DURATION_MS = 30_000;

    public static function countsAsStream(int $durationMs): bool
    {
        return $durationMs >= self::MIN_STREAM_DURATION_MS;
    }
}
