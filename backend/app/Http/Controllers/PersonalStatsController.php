<?php

namespace App\Http\Controllers;

use App\Models\PlayRecord;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalStatsController extends Controller
{
    private function window(Request $request): string
    {
        return $request->query('window', 'week');
    }

    private function validateWindow(Request $request)
    {
        $request->validate([
            'window' => [Rule::in(['day', 'week', 'month', 'year'])],
        ]);
    }

    /**
     * Which Spotify (Stats.fm) connection's plays these stats should be
     * scoped to. A user can have several connected at once now, and their
     * stats must never mix — so every one of the endpoints below resolves
     * this the same way and passes it straight into
     * PlayRecord::scopeForStatsFmConnection():
     *
     *   - `statsfm_connection_id` query param, if given, is used as-is
     *     (after confirming it actually belongs to this user — otherwise
     *     a stale id from an old tab could leak another linked account's
     *     numbers into this one's stats, or 404 rather than silently
     *     falling back).
     *   - Otherwise, fall back to the user's default connection
     *     (earliest-connected) — this is what keeps a single-account
     *     user's dashboard behaving exactly as it did before this
     *     feature existed, with zero client changes required.
     *   - A user with zero Spotify connections gets null back, which
     *     scopeForStatsFmConnection() treats as "don't restrict" — there
     *     is nothing to disambiguate between, and Apple Music/Musicat
     *     stats (which aren't affected by any of this) still need to come
     *     through.
     */
    private function resolveStatsFmConnectionId(Request $request): ?int
    {
        $requested = $request->query('statsfm_connection_id');

        if ($requested !== null) {
            $owned = $request->user()->statsFmConnections()->whereKey($requested)->exists();

            abort_unless($owned, 404, 'No such connected Spotify account.');

            return (int) $requested;
        }

        return $request->user()->defaultStatsFmConnection()?->id;
    }

    public function topTracks(Request $request)
    {
        $this->validateWindow($request);
        $userId = $request->user()->id;
        $window = $this->window($request);
        $connectionId = $this->resolveStatsFmConnectionId($request);

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
            ->matchingConnectedSource()
            ->forStatsFmConnection($connectionId)
            ->inWindow($window)
            // See PublicDashboardController::topTracks() for why
            // album_name/artwork_url must be aggregated with MAX() rather
            // than grouped on directly — grouping on them fragments a
            // single track into an "has artwork" row and a "no artwork"
            // row whenever a play was scraped before its thumbnail loaded,
            // and the artwork-less fragment usually wins on play_count.
            ->selectRaw('track_id, track_name, artist_name, MAX(album_name) as album_name, MAX(artwork_url) as artwork_url, source,
                COUNT(*) as play_count, SUM(duration_ms) as total_ms')
            ->groupBy('track_id', 'track_name', 'artist_name', 'source')
            ->orderByDesc('play_count')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'track_id' => $row->track_id,
                'track_name' => $row->track_name,
                'artist_name' => $row->artist_name,
                'album_name' => $row->album_name,
                'artwork_url' => $row->artwork_url,
                'source' => $row->source,
                'play_count' => (int) $row->play_count,
                'total_ms' => (int) $row->total_ms,
                'total_time_formatted' => Duration::humanize((int) $row->total_ms),
            ]);

        return response()->json(['window' => $window, 'statsfm_connection_id' => $connectionId, 'tracks' => $rows]);
    }

    public function topArtists(Request $request)
    {
        $this->validateWindow($request);
        $userId = $request->user()->id;
        $window = $this->window($request);
        $connectionId = $this->resolveStatsFmConnectionId($request);

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
            ->matchingConnectedSource()
            ->forStatsFmConnection($connectionId)
            ->inWindow($window)
            ->selectRaw('artist_id, artist_name, MAX(artist_image_url) as artist_image_url,
                COUNT(*) as play_count, SUM(duration_ms) as total_ms,
                COUNT(DISTINCT track_id) as track_count')
            ->groupBy('artist_id', 'artist_name')
            ->orderByDesc('play_count')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'artist_id' => $row->artist_id,
                'artist_name' => $row->artist_name,
                'artist_image_url' => $row->artist_image_url,
                'play_count' => (int) $row->play_count,
                'track_count' => (int) $row->track_count,
                'total_ms' => (int) $row->total_ms,
                'total_time_formatted' => Duration::humanize((int) $row->total_ms),
            ]);

        return response()->json(['window' => $window, 'statsfm_connection_id' => $connectionId, 'artists' => $rows]);
    }

    public function recentlyPlayed(Request $request)
    {
        $userId = $request->user()->id;
        $connectionId = $this->resolveStatsFmConnectionId($request);

        // Deliberately no ->allowedArtists() here: the counted stats
        // (topTracks/topArtists above) stay BLACKPINK+members-only, but
        // "recently played" is a log of what was actually played and
        // shouldn't drop rows just because a feature/collab artist isn't
        // on that list.
        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->matchingConnectedSource()
            ->forStatsFmConnection($connectionId)
            ->orderByDesc('played_at')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'track_id' => $row->track_id,
                'track_name' => $row->track_name,
                'artist_name' => $row->artist_name,
                'album_name' => $row->album_name,
                'artwork_url' => $row->artwork_url,
                'source' => $row->source,
                'duration_formatted' => Duration::humanize($row->duration_ms),
                'played_at' => $row->played_at->toIso8601String(),
            ]);

        return response()->json(['statsfm_connection_id' => $connectionId, 'recently_played' => $rows]);
    }

    /**
     * Daily play counts per source for the trailing 30 days, used to power
     * the "Last 30 days" activity graph. Days with no plays are included
     * with a count of 0 so the chart has a continuous x-axis.
     */
    public function dailyActivity(Request $request)
    {
        $userId = $request->user()->id;
        $connectionId = $this->resolveStatsFmConnectionId($request);
        $start = now()->subDays(29)->startOfDay();

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
            ->matchingConnectedSource()
            ->forStatsFmConnection($connectionId)
            ->where('played_at', '>=', $start)
            ->selectRaw('DATE(played_at) as day, source, COUNT(*) as play_count')
            ->groupBy('day', 'source')
            ->get();

        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $days[$date] = ['date' => $date, 'spotify' => 0, 'apple_music' => 0];
        }

        foreach ($rows as $row) {
            if (! isset($days[$row->day])) {
                continue;
            }
            $key = $row->source === 'apple_music' ? 'apple_music' : 'spotify';
            $days[$row->day][$key] = (int) $row->play_count;
        }

        return response()->json(['statsfm_connection_id' => $connectionId, 'days' => array_values($days)]);
    }
}