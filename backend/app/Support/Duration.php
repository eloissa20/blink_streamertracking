<?php

namespace App\Support;

class Duration
{
    /**
     * Format milliseconds like Spotify Wrapped does: "12h 34m".
     * Falls back to minutes-only ("34m") or "<1m" for very short totals.
     */
    public static function humanize(int $ms): string
    {
        $totalMinutes = intdiv($ms, 60000);

        if ($totalMinutes < 1) {
            return '<1m';
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours === 0) {
            return "{$minutes}m";
        }

        return "{$hours}h {$minutes}m";
    }
}
