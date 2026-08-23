<?php

namespace Database\Seeders;

use App\Models\StreamerAchievement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TestAchievementSeeder extends Seeder
{
    /**
     * Populates test@example.com's achievement history directly, so
     * /achievements and the Dashboard have realistic sample cards to
     * show without needing any real synced streams.
     *
     * These rows bypass the normal path entirely (StreamerLevelController
     * normally computes levels from play_records) — they're inserted
     * straight into streamer_achievements, purely for demoing the UI.
     * image_url points at a neutral placehold.co color swatch, not real
     * BLACKPINK artwork or photos — this is only here to prove the image
     * slot in the card actually renders when a URL is present. Once real
     * plays are synced, StreamerLevelController naturally fills this
     * field with the genuine artist_image_url/artwork_url from Spotify/
     * Apple Music instead (see currentCounters()).
     * Because they're pre-existing rows rather than newly-crossed levels,
     * they populate the Achievements gallery immediately but won't
     * trigger the LevelUpCard popup — that only fires for a level that's
     * newly crossed *during* a GET /me/streamer-levels call.
     *
     * Safe to run repeatedly: matches on (user_id, achievement_key,
     * level), same uniqueness rule the real table enforces, so it never
     * creates duplicates. Uses updateOrCreate (not firstOrCreate) so
     * re-running this after editing a sample above — e.g. adding an
     * image_url — actually refreshes the existing row instead of
     * silently leaving it as it was the first time you seeded.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $this->command?->warn('No test@example.com user found — run the main seeder first.');

            return;
        }

        $now = Carbon::now();

        $samples = [
            // BLACKPINK group — overall total, Junior tier
            [
                'achievement_key' => 'artist:blackpink',
                'type' => 'artist',
                'artist_name' => 'BLACKPINK',
                'member_name' => null,
                'song_title' => null,
                'level' => 6,
                'tier' => 'Junior Streamer',
                'total_streams_at_unlock' => 500,
                'image_url' => 'https://placehold.co/128x128/0A0A0C/FF2E93?text=BP',
            ],
            // BLACKPINK's own song — Real tier
            [
                'achievement_key' => 'song:sample-jump',
                'type' => 'solo',
                'artist_name' => 'BLACKPINK',
                'member_name' => null,
                'song_title' => 'Jump',
                'level' => 14,
                'tier' => 'Real Streamer',
                'total_streams_at_unlock' => 12_000,
                'image_url' => 'https://placehold.co/128x128/0A0A0C/FF2E93?text=Jump',
            ],
            // Jennie — solo overall, Real tier
            [
                'achievement_key' => 'artist:jennie',
                'type' => 'solo',
                'artist_name' => 'Jennie',
                'member_name' => 'Jennie',
                'song_title' => null,
                'level' => 18,
                'tier' => 'Real Streamer',
                'total_streams_at_unlock' => 40_000,
                'image_url' => 'https://placehold.co/128x128/05191C/02D8E9?text=J',
            ],
            // Jisoo — a song, Junior tier
            [
                'achievement_key' => 'song:sample-flower',
                'type' => 'solo',
                'artist_name' => 'Jisoo',
                'member_name' => 'Jisoo',
                'song_title' => 'Flower',
                'level' => 8,
                'tier' => 'Junior Streamer',
                'total_streams_at_unlock' => 1_000,
                'image_url' => 'https://placehold.co/128x128/211B2E/C9A9E8?text=Flower',
            ],
            // Rosé — solo overall, Pro tier
            [
                'achievement_key' => 'artist:rose',
                'type' => 'solo',
                'artist_name' => 'Rosé',
                'member_name' => 'Rosé',
                'song_title' => null,
                'level' => 41,
                'tier' => 'Pro Streamer',
                'total_streams_at_unlock' => 5_000_000,
                'image_url' => 'https://placehold.co/128x128/17161A/FFFFFF?text=R',
            ],
            // Lisa — a song, Real tier
            [
                'achievement_key' => 'song:sample-rockstar',
                'type' => 'solo',
                'artist_name' => 'Lisa',
                'member_name' => 'Lisa',
                'song_title' => 'Rockstar',
                'level' => 22,
                'tier' => 'Real Streamer',
                'total_streams_at_unlock' => 140_000,
                'image_url' => 'https://placehold.co/128x128/141312/F5D400?text=Rockstar',
            ],
        ];

        foreach ($samples as $sample) {
            StreamerAchievement::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'achievement_key' => $sample['achievement_key'],
                    'level' => $sample['level'],
                ],
                $sample + [
                    'user_id' => $user->id,
                    'achieved_at' => $now,
                ]
            );
        }
    }
}