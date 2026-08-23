<?php

namespace App\Http\Controllers;

use App\Models\PlayRecord;
use App\Models\StreamerAchievement;
use App\Support\AllowedArtists;
use App\Support\StreamerLevels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StreamerLevelController extends Controller
{
    /**
     * The Streamer Level system's source of truth. Every call:
     *  1. Recomputes this user's real stream counts (group total, each
     *     member's solo total, and every allowed-artist song they've
     *     played) straight from play_records.
     *  2. Diffs each counter's computed level against whatever's already
     *     stored in streamer_achievements for this user.
     *  3. Persists a row for every newly-crossed level (never mutates or
     *     removes existing rows, so history is permanent even if a sync
     *     is ever replayed).
     *  4. Returns the full unlocked history plus, separately, just the
     *     levels that were newly unlocked by *this* request — the
     *     frontend feeds that second list straight into the popup queue.
     *
     * Persisting here (rather than trusting the frontend to remember via
     * localStorage) is what makes achievements follow the user across
     * devices/browsers instead of re-popping on a new machine.
     */
    public function index()
    {
        $user = auth()->user();

        $counters = $this->currentCounters($user->id);

        $existingMaxLevels = StreamerAchievement::where('user_id', $user->id)
            ->select('achievement_key', DB::raw('MAX(level) as max_level'))
            ->groupBy('achievement_key')
            ->pluck('max_level', 'achievement_key');

        $newlyUnlocked = [];
        $now = Carbon::now();

        foreach ($counters as $counter) {
            $info = StreamerLevels::forStreams($counter['total_streams']);
            $previousLevel = $existingMaxLevels[$counter['key']] ?? -1;

            if ($info['level'] <= $previousLevel || $info['level'] < 1) {
                continue;
            }

            // Credit every level in between, not just the newest, in case
            // the user jumped several levels since the last time this
            // ran (e.g. after a large "Sync now" catch-up).
            for ($level = $previousLevel + 1; $level <= $info['level']; $level++) {
                $tier = StreamerLevels::tierForLevel($level);

                $achievement = StreamerAchievement::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'achievement_key' => $counter['key'],
                        'level' => $level,
                    ],
                    [
                        'type' => $counter['type'],
                        'artist_name' => $counter['artist_name'],
                        'member_name' => $counter['member_name'],
                        'song_title' => $counter['song_title'],
                        'image_url' => $counter['image_url'],
                        'tier' => $tier,
                        'total_streams_at_unlock' => $counter['total_streams'],
                        'achieved_at' => $now,
                    ]
                );

                // Only the highest newly-crossed level per counter goes
                // into the popup queue — the user doesn't need to see six
                // stacked cards because they leveled up six times between
                // visits, just the level they're at now.
                if ($level === $info['level']) {
                    $newlyUnlocked[] = $this->presentAchievement($achievement);
                }
            }
        }

        $allAchievements = StreamerAchievement::where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->get()
            ->map(fn ($a) => $this->presentAchievement($a));

        return response()->json([
            'achievements' => $allAchievements,
            'newly_unlocked' => $newlyUnlocked,
        ]);
    }

    private function presentAchievement(StreamerAchievement $a): array
    {
        return [
            'key' => $a->achievement_key,
            'type' => $a->type,
            'level' => $a->level,
            'tier' => $a->tier,
            'artist_name' => $a->artist_name,
            'member_name' => $a->member_name,
            'song_title' => $a->song_title,
            'image_url' => $a->image_url,
            'total_streams' => $a->total_streams_at_unlock,
            'achieved_at' => $a->achieved_at->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{
     *   key: string, type: 'artist'|'solo', artist_name: string,
     *   member_name: ?string, song_title: ?string, image_url: ?string,
     *   total_streams: int
     * }>
     */
    private function currentCounters(int $userId): array
    {
        $base = fn () => PlayRecord::where('user_id', $userId)
            ->allowedArtists()
            ->matchingConnectedSource();

        $counters = [];

        // One counter per allowed artist: BLACKPINK itself is the
        // "artist" (group) card; each member is a "solo" card even
        // though it's their *overall* total, not a single song.
        foreach (AllowedArtists::NAMES as $name) {
            // MAX() over a string column just needs *a* non-null value —
            // every play for a given artist should carry the same
            // artist_image_url anyway, so which row wins doesn't matter.
            $row = (clone $base())
                ->whereRaw('UPPER(artist_name) = ?', [mb_strtoupper($name, 'UTF-8')])
                ->selectRaw('COUNT(*) as stream_count, MAX(artist_image_url) as image_url')
                ->first();

            $counters[] = [
                'key' => 'artist:'.strtolower($name),
                'type' => $name === 'BLACKPINK' ? 'artist' : 'solo',
                'artist_name' => $name,
                'member_name' => $name === 'BLACKPINK' ? null : $name,
                'song_title' => null,
                'image_url' => $row->image_url,
                'total_streams' => (int) $row->stream_count,
            ];
        }

        // One counter per song this user has actually played, for every
        // allowed artist — including BLACKPINK's own tracks, not just
        // solo members' songs, so a group song gets its own Song
        // Milestone card too instead of only ever counting toward the
        // group's combined overall total.
        $songRows = (clone $base())
            ->selectRaw('track_id, track_name, artist_name, MAX(artwork_url) as artwork_url, COUNT(*) as stream_count')
            ->groupBy('track_id', 'track_name', 'artist_name')
            ->get();

        foreach ($songRows as $row) {
            $counters[] = [
                'key' => 'song:'.$row->track_id,
                'type' => 'solo',
                'artist_name' => $row->artist_name,
                'member_name' => $row->artist_name === 'BLACKPINK' ? null : $row->artist_name,
                'song_title' => $row->track_name,
                'image_url' => $row->artwork_url,
                'total_streams' => (int) $row->stream_count,
            ];
        }

        return $counters;
    }
}
