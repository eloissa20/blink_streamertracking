<?php

namespace App\Console\Commands;

use App\Models\StatsFmConnection;
use App\Services\PlayRecordSyncer;
use Illuminate\Console\Command;

class SyncStatsFmAccounts extends Command
{
    protected $signature = 'statsfm:sync';
    protected $description = 'Pull recently-played data for every connected Stats.fm account';

    public function handle(PlayRecordSyncer $syncer): int
    {
        $connections = StatsFmConnection::all();
        $this->info("Syncing {$connections->count()} connected accounts...");

        foreach ($connections as $connection) {
            $inserted = $syncer->sync($connection);
            $this->line(" - {$connection->statsfm_username}: {$inserted} new plays");
        }

        return self::SUCCESS;
    }
}
