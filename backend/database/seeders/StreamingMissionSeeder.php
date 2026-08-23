<?php

namespace Database\Seeders;

use App\Models\StreamingMission;
use Illuminate\Database\Seeder;

class StreamingMissionSeeder extends Seeder
{
    /**
     * A few starter missions so the Missions tab isn't empty on a fresh
     * install. Safe to run repeatedly — matches on title so it won't
     * create duplicates.
     */
    public function run(): void
    {
        $missions = [
            [
                'title' => '10K for "Jump"',
                'description' => 'Help BLACKPINK\'s "Jump" hit 10,000 tracked streams from everyone using this app.',
                'artist_name' => 'BLACKPINK',
                'track_name' => 'Jump',
                'target_streams' => 10_000,
                'theme_key' => 'blackpink',
            ],
            [
                'title' => 'ROSÉ Solo Push',
                'description' => 'Get ROSÉ\'s solo total to 50,000 streams across every tracked account.',
                'artist_name' => 'ROSÉ',
                'track_name' => null,
                'target_streams' => 50_000,
                'theme_key' => 'rose',
            ],
        ];

        foreach ($missions as $mission) {
            StreamingMission::firstOrCreate(
                ['title' => $mission['title']],
                $mission
            );
        }
    }
}
