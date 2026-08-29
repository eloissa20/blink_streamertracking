<?php

namespace App\Http\Controllers;

use App\Models\StreamingMission;
use Illuminate\Support\Facades\Cache;

class StreamingMissionController extends Controller
{
    /**
     * Every currently-open mission plus its live progress. Deliberately
     * counts plays from every user in the app, not just the one making
     * the request — a mission is a shared community goal, so contributing
     * a stream from any connected account moves every viewer's copy of
     * the same progress bar.
     *
     * This does NOT respect the public-overview opt-in flag used by
     * PublicDashboardController — that flag only governs what an
     * anonymous visitor to the public landing page can see. Missions are
     * an authenticated, internal feature, so every logged-in user's
     * tracked plays count here regardless of that setting.
     */
    public function index()
    {
        $missions = StreamingMission::currentlyOpen()->orderBy('created_at')->get();

        $payload = $missions->map(function (StreamingMission $mission) {
            return Cache::remember(
                "streaming_mission_progress:{$mission->id}",
                30,
                function () use ($mission) {
                    $current = $mission->matchingPlaysQuery()->count();
                    $contributors = $mission->matchingPlaysQuery()->distinct('user_id')->count('user_id');

                    return [
                        'id' => $mission->id,
                        'title' => $mission->title,
                        'description' => $mission->description,
                        'artist_name' => $mission->artist_name,
                        'track_name' => $mission->track_name,
                        'artwork_url' => $mission->artwork_url,
                        'theme_key' => $mission->theme_key,
                        'source' => $mission->source,
                        'is_per_song' => $mission->isPerSong(),
                        'target_streams' => $mission->target_streams,
                        'current_streams' => $current,
                        'progress' => $mission->target_streams > 0
                            ? min(1, $current / $mission->target_streams)
                            : 0,
                        'contributors' => $contributors,
                        'is_complete' => $current >= $mission->target_streams,
                        'ends_at' => $mission->ends_at?->toIso8601String(),
                    ];
                }
            );
        });

        return response()->json(['missions' => $payload]);
    }
}
