<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\GmailAddress;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // email_canonical has to be passed explicitly here too — the
        // factory's default only canonicalizes the random fake() email it
        // generates internally, before this override array replaces
        // 'email' with the fixed test address below.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_canonical' => GmailAddress::canonicalize('test@example.com'),
        ]);

        $this->call(StreamingMissionSeeder::class);
    }
}
