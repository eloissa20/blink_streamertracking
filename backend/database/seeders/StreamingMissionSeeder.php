<?php

namespace Database\Seeders;

use App\Models\StreamingMission;
use Illuminate\Database\Seeder;

class StreamingMissionSeeder extends Seeder
{
    /**
     * The active streaming missions. Safe to run repeatedly — matches on
     * `slug` (a stable id, unlike the free-text title) so re-running this
     * updates the missions below in place rather than creating
     * duplicates, and never touches user progress: progress isn't stored
     * per-mission anywhere, it's recomputed live from play_records by
     * matching artist_name/track_name/source (see StreamingMission::
     * matchingPlaysQuery()), so a fresh mission with the right artist/
     * track/source picks up any streams that already exist for it —
     * nothing needs to be "migrated".
     *
     * Every song here is split into TWO separate missions — one per
     * platform (`source` => 'spotify' or 'apple_music') — rather than one
     * combined mission that adds both together. A stream only ever moves
     * the progress bar of the one mission matching its own platform;
     * Spotify streams never add to the Apple Music mission's number or
     * vice versa. Both still pool every user's plays on that platform
     * (this isn't per-user — see StreamingMissionController::index()).
     *
     * $songs below is the shared data (title/description/artist/track/
     * target/theme) for one song; the loop below expands each into its
     * two platform-specific mission rows so the two don't drift out of
     * sync with each other over time.
     */
    public function run(): void
    {
        $songs = [
            [
                'slug' => 'jennie-heaven',
                'title' => 'Jennie Heaven',
                'artist_name' => 'Jennie',
                'track_name' => 'Heaven',
                'target_streams' => 10_000,
                'theme_key' => 'jennie',
            ],
            [
                'slug' => 'jisoo-click',
                'title' => 'Jisoo Click',
                'artist_name' => 'Jisoo',
                'track_name' => 'Click',
                'target_streams' => 10_000,
                'theme_key' => 'jisoo',
            ],
            [
                'slug' => 'lisa-sawadika',
                'title' => 'Lisa SaWaDiKa',
                'artist_name' => 'Lisa',
                'track_name' => 'SaWaDiKa',
                'target_streams' => 10_000,
                'theme_key' => 'lisa',
            ],
            [
                'slug' => 'rose-apt',
                'title' => 'ROSÉ APT.',
                'artist_name' => 'ROSÉ',
                'track_name' => 'APT.',
                'target_streams' => 10_000,
                'theme_key' => 'rose',
            ],
            [
                'slug' => 'blackpink-jump',
                'title' => 'BLACKPINK JUMP',
                'artist_name' => 'BLACKPINK',
                'track_name' => 'JUMP',
                'target_streams' => 10_000,
                'theme_key' => 'blackpink',
            ],
        ];

        $platforms = [
            'spotify' => 'Spotify',
            'apple_music' => 'Apple Music',
        ];

        $missions = [];

        foreach ($songs as $song) {
            foreach ($platforms as $source => $platformLabel) {
                $missions[] = [
                    'slug' => "{$song['slug']}-{$source}",
                    'title' => "{$song['title']} — {$platformLabel}",
                    'description' => "Help {$song['artist_name']}'s \"{$song['track_name']}\" hit "
                        .number_format($song['target_streams'])." tracked {$platformLabel} streams from everyone using this app.",
                    'artist_name' => $song['artist_name'],
                    'track_name' => $song['track_name'],
                    'target_streams' => $song['target_streams'],
                    'theme_key' => $song['theme_key'],
                    'source' => $source,
                ];
            }
        }

        $activeSlugs = array_column($missions, 'slug');

        foreach ($missions as $mission) {
            $mission['is_active'] = true;

            StreamingMission::updateOrCreate(
                ['slug' => $mission['slug']],
                $mission
            );
        }

        // Archive (deactivate, don't delete) every other mission —
        // including the old seed missions ('10K for "Jump"', 'ROSÉ Solo
        // Push') and the pre-split combined missions (e.g. the old
        // 'jennie-heaven' slug, now superseded by 'jennie-heaven-spotify'
        // and 'jennie-heaven-apple_music') — so only the missions above
        // show up as active, while old rows (and any history/cache tied
        // to their ids) stay intact.
        StreamingMission::whereNotIn('slug', $activeSlugs)
            ->orWhereNull('slug')
            ->update(['is_active' => false]);
    }
}
