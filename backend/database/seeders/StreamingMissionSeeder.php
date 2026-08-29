<?php

namespace Database\Seeders;

use App\Models\StreamingMission;
use Illuminate\Database\Seeder;

class StreamingMissionSeeder extends Seeder
{
    /**
     * The active streaming missions. Safe to run repeatedly — matches on
     * `slug` (a stable id, unlike the free-text title) so re-running this
     * updates the 5 missions below in place rather than creating
     * duplicates, and never touches user progress: progress isn't stored
     * per-mission anywhere, it's recomputed live from play_records by
     * matching artist_name/track_name (see StreamingMission::
     * matchingPlaysQuery()), so a fresh mission with the right artist/
     * track name picks up any streams that already exist for it —
     * nothing needs to be "migrated".
     *
     * Every mission here has no per-platform split: missions already
     * match streams from every connected source (Spotify + Apple Music
     * combined) via matchingConnectedSource(), so there's no separate
     * Spotify-only / Apple-Music-only mission to create.
     */
    public function run(): void
    {
        $missions = [
            [
                'slug' => 'jennie-heaven',
                'title' => 'Jennie Heaven',
                'description' => 'Help Jennie\'s "Heaven" hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'Jennie',
                'track_name' => 'Heaven',
                'target_streams' => 10_000,
                'theme_key' => 'jennie',
            ],
            [
                'slug' => 'jisoo-click',
                'title' => 'Jisoo Click',
                'description' => 'Help Jisoo\'s "Click" hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'Jisoo',
                'track_name' => 'Click',
                'target_streams' => 10_000,
                'theme_key' => 'jisoo',
            ],
            [
                'slug' => 'lisa-sawadika',
                'title' => 'Lisa SaWaDiKa',
                'description' => 'Help Lisa\'s "SaWaDiKa" hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'Lisa',
                'track_name' => 'SaWaDiKa',
                'target_streams' => 10_000,
                'theme_key' => 'lisa',
            ],
            [
                'slug' => 'rose-apt',
                'title' => 'ROSÉ APT.',
                'description' => 'Help ROSÉ\'s "APT." hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'ROSÉ',
                'track_name' => 'APT.',
                'target_streams' => 10_000,
                'theme_key' => 'rose',
            ],
            [
                'slug' => 'blackpink-jump',
                'title' => 'BLACKPINK JUMP',
                'description' => 'Help BLACKPINK\'s "JUMP" hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'BLACKPINK',
                'track_name' => 'JUMP',
                'target_streams' => 10_000,
                'theme_key' => 'blackpink',
            ],
        ];

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
        // Push') — so only the 5 above show up as active, while old rows
        // (and any history/cache tied to their ids) stay intact.
        StreamingMission::whereNotIn('slug', $activeSlugs)
            ->orWhereNull('slug')
            ->update(['is_active' => false]);
    }
}
