<?php

namespace App\Http\Controllers;

use App\Models\MusicatConnection;
use App\Models\PlayRecord;
use App\Models\StatsFmConnection;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PublicDashboardController extends Controller
{
    /**
     * The public page is a tab switcher between two completely separate
     * platform views (Spotify via Stats.fm, Apple Music via Musicat).
     * Every public endpoint is scoped to exactly one of these at a time
     * so the two tabs never mix data — the request must say which one it
     * wants via ?platform=spotify|apple_music.
     */
    private const PLATFORM_SOURCES = [
        'spotify' => 'spotify',
        'apple_music' => 'apple_music',
    ];

    private function platform(Request $request): string
    {
        $request->validate([
            'platform' => ['required', Rule::in(array_keys(self::PLATFORM_SOURCES))],
        ]);

        return $request->query('platform');
    }

    /**
     * Only plays from users who opted their connection(s) into the public
     * overview are counted here — a user contributes if either their
     * Stats.fm (Spotify) connection, their Musicat (Apple Music)
     * connection, or both, have public sharing turned on.
     *
     * Always scoped to a single platform's `source` on top of that, so a
     * request for the Spotify tab can never pull in an Apple Music row
     * (or vice versa) even though both connection types can belong to the
     * same opted-in user.
     */
    private function baseQuery(string $platform)
    {
        $source = self::PLATFORM_SOURCES[$platform];

        $optedInUserIds = StatsFmConnection::where('include_in_public_overview', true)
            ->pluck('user_id')
            ->merge(
                MusicatConnection::where('include_in_public_overview', true)->pluck('user_id')
            )
            ->unique();

        return PlayRecord::query()
            ->whereIn('user_id', $optedInUserIds)
            ->where('source', $source)
            ->allowedArtists()
            ->matchingConnectedSource();
    }

    public function overview(Request $request)
    {
        $platform = $this->platform($request);

        return response()->json(
            Cache::remember("public_dashboard_overview:{$platform}", 60, function () use ($platform) {
                return [
                    'platform' => $platform,
                    'total_streams' => [
                        'all_time' => (clone $this->baseQuery($platform))->count(),
                        'today' => (clone $this->baseQuery($platform))->where('played_at', '>=', Carbon::now()->startOfDay())->count(),
                        'this_week' => (clone $this->baseQuery($platform))->where('played_at', '>=', Carbon::now()->startOfWeek())->count(),
                        'this_month' => (clone $this->baseQuery($platform))->where('played_at', '>=', Carbon::now()->startOfMonth())->count(),
                        'this_year' => (clone $this->baseQuery($platform))->where('played_at', '>=', Carbon::now()->startOfYear())->count(),
                    ],
                    'contributors' => (clone $this->baseQuery($platform))
                        ->distinct('user_id')
                        ->count('user_id'),
                ];
            })
        );
    }

    public function topTracks(Request $request)
    {
        $platform = $this->platform($request);

        $tracks = Cache::remember("public_dashboard_top_tracks:{$platform}", 60, function () use ($platform) {
            $tracks = (clone $this->baseQuery($platform))
                ->selectRaw('track_id, track_name, artist_name, album_name, artwork_url, source, COUNT(*) as stream_count')
                ->groupBy('track_id', 'track_name', 'artist_name', 'album_name', 'artwork_url', 'source')
                ->orderByDesc('stream_count')
                ->limit(25)
                ->get();

            $trackIds = $tracks->pluck('track_id')->all();
            $todayStart = Carbon::now()->startOfDay();
            $yesterdayStart = (clone $todayStart)->subDay();

            // Per-track streams picked up today vs. yesterday, so the
            // public page can show each track's daily contribution
            // ("+123") and whether it's trending up or down against
            // yesterday's number.
            $todayCounts = (clone $this->baseQuery($platform))
                ->whereIn('track_id', $trackIds)
                ->where('played_at', '>=', $todayStart)
                ->selectRaw('track_id, COUNT(*) as c')
                ->groupBy('track_id')
                ->pluck('c', 'track_id');

            $yesterdayCounts = (clone $this->baseQuery($platform))
                ->whereIn('track_id', $trackIds)
                ->where('played_at', '>=', $yesterdayStart)
                ->where('played_at', '<', $todayStart)
                ->selectRaw('track_id, COUNT(*) as c')
                ->groupBy('track_id')
                ->pluck('c', 'track_id');

            return $tracks->map(function ($track) use ($todayCounts, $yesterdayCounts) {
                $track->today_count = (int) ($todayCounts[$track->track_id] ?? 0);
                $track->yesterday_count = (int) ($yesterdayCounts[$track->track_id] ?? 0);

                return $track;
            });
        });

        return response()->json(['platform' => $platform, 'tracks' => $tracks]);
    }

    public function topArtists(Request $request)
    {
        $platform = $this->platform($request);

        $artists = Cache::remember("public_dashboard_top_artists:{$platform}", 60, function () use ($platform) {
            return (clone $this->baseQuery($platform))
                ->selectRaw('artist_id, artist_name, MAX(artist_image_url) as artist_image_url,
                    COUNT(*) as stream_count, COUNT(DISTINCT track_id) as track_count')
                ->groupBy('artist_id', 'artist_name')
                ->orderByDesc('stream_count')
                ->limit(25)
                ->get();
        });

        return response()->json(['platform' => $platform, 'artists' => $artists]);
    }

    /**
     * Public "recently played", scoped to one platform just like the rest
     * of this controller. Deliberately does NOT go through baseQuery()'s
     * ->allowedArtists() filter — "Recently played" is a log of everything
     * an opted-in user actually played, on both the personal My Stats page
     * (see PersonalStatsController::recentlyPlayed) and here on the public
     * page. Only Top Tracks/Top Artists are restricted to BLACKPINK and
     * its members; Recently Played always shows the full unfiltered
     * history for opted-in users on this platform.
     */
    public function recentlyPlayed(Request $request)
    {
        $platform = $this->platform($request);
        $source = self::PLATFORM_SOURCES[$platform];

        $rows = Cache::remember("public_dashboard_recently_played:{$platform}", 60, function () use ($platform, $source) {
            $optedInUserIds = StatsFmConnection::where('include_in_public_overview', true)
                ->pluck('user_id')
                ->merge(
                    MusicatConnection::where('include_in_public_overview', true)->pluck('user_id')
                )
                ->unique();

            return PlayRecord::query()
                ->whereIn('user_id', $optedInUserIds)
                ->where('source', $source)
                ->matchingConnectedSource()
                ->orderByDesc('played_at')
                ->limit(50)
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
        });

        return response()->json(['platform' => $platform, 'recently_played' => $rows]);
    }
}