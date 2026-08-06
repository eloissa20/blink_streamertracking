<?php

namespace App\Http\Controllers;

use App\Models\PlayRecord;
use App\Models\StatsFmConnection;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PublicDashboardController extends Controller
{
    /**
     * Only plays from users who opted their Stats.fm connection into the
     * public overview are counted here.
     */
    private function baseQuery()
    {
        $optedInUserIds = StatsFmConnection::where('include_in_public_overview', true)
            ->pluck('user_id');

        return PlayRecord::query()->whereIn('user_id', $optedInUserIds)->allowedArtists()->matchingConnectedSource();
    }

    public function overview()
    {
        return response()->json(
            Cache::remember('public_dashboard_overview', 60, function () {
                return [
                    'total_streams' => [
                        'all_time' => (clone $this->baseQuery())->count(),
                        'today' => (clone $this->baseQuery())->where('played_at', '>=', Carbon::now()->startOfDay())->count(),
                        'this_week' => (clone $this->baseQuery())->where('played_at', '>=', Carbon::now()->startOfWeek())->count(),
                        'this_month' => (clone $this->baseQuery())->where('played_at', '>=', Carbon::now()->startOfMonth())->count(),
                        'this_year' => (clone $this->baseQuery())->where('played_at', '>=', Carbon::now()->startOfYear())->count(),
                    ],
                    'contributors' => StatsFmConnection::where('include_in_public_overview', true)->count(),
                ];
            })
        );
    }

    public function topTracks()
    {
        $tracks = Cache::remember('public_dashboard_top_tracks', 60, function () {
            return (clone $this->baseQuery())
                ->selectRaw('track_id, track_name, artist_name, album_name, artwork_url, source, COUNT(*) as stream_count')
                ->groupBy('track_id', 'track_name', 'artist_name', 'album_name', 'artwork_url', 'source')
                ->orderByDesc('stream_count')
                ->limit(25)
                ->get();
        });

        return response()->json(['tracks' => $tracks]);
    }

    public function topArtists()
    {
        $artists = Cache::remember('public_dashboard_top_artists', 60, function () {
            return (clone $this->baseQuery())
                ->selectRaw('artist_id, artist_name, MAX(artist_image_url) as artist_image_url,
                    COUNT(*) as stream_count, COUNT(DISTINCT track_id) as track_count')
                ->groupBy('artist_id', 'artist_name')
                ->orderByDesc('stream_count')
                ->limit(25)
                ->get();
        });

        return response()->json(['artists' => $artists]);
    }
}
