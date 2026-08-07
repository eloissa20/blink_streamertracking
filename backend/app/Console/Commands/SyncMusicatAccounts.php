<?php

namespace App\Console\Commands;

use App\Models\MusicatConnection;
use App\Services\MusicatPlayRecordSyncer;
use Illuminate\Console\Command;

class SyncMusicatAccounts extends Command
{
    protected $signature = 'musicat:sync';
    protected $description = 'Pull recently-played Apple Music data for every connected Musicat account';

    public function handle(MusicatPlayRecordSyncer $syncer): int
    {
        $connections = MusicatConnection::all();
        $this->info("Syncing {$connections->count()} connected Musicat accounts...");

        foreach ($connections as $connection) {
            $inserted = $syncer->sync($connection);
            $this->line(" - {$connection->musicat_username}: {$inserted} new plays");
        }

        return self::SUCCESS;
    }
}
