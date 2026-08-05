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

    public function topTracks(Request $request)
    {
        $this->validateWindow($request);
        $userId = $request->user()->id;
        $window = $this->window($request);

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
            ->inWindow($window)
            ->selectRaw('track_id, track_name, artist_name, album_name, artwork_url, source,
                COUNT(*) as play_count, SUM(duration_ms) as total_ms')
            ->groupBy('track_id', 'track_name', 'artist_name', 'album_name', 'artwork_url', 'source')
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

        return response()->json(['window' => $window, 'tracks' => $rows]);
    }

    public function topArtists(Request $request)
    {
        $this->validateWindow($request);
        $userId = $request->user()->id;
        $window = $this->window($request);

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
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

        return response()->json(['window' => $window, 'artists' => $rows]);
    }

    public function recentlyPlayed(Request $request)
    {
        $userId = $request->user()->id;

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
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

        return response()->json(['recently_played' => $rows]);
    }

    /**
     * Daily play counts per source for the trailing 30 days, used to power
     * the "Last 30 days" activity graph. Days with no plays are included
     * with a count of 0 so the chart has a continuous x-axis.
     */
    public function dailyActivity(Request $request)
    {
        $userId = $request->user()->id;
        $start = now()->subDays(29)->startOfDay();

        $rows = PlayRecord::query()
            ->where('user_id', $userId)
            ->allowedArtists()
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

        return response()->json(['days' => array_values($days)]);
    }
}
