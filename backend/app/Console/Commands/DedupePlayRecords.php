<?php

namespace App\Console\Commands;

use App\Models\PlayRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupePlayRecords extends Command
{
    protected $signature = 'playrecords:dedupe {--dry-run : Show what would be deleted without deleting anything}';
    protected $description = 'One-time cleanup: remove duplicate play records (same connection + track + played_at) left over from the sync id bug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Group by the fields that actually identify a unique play, and for
        // any group with more than one row, keep the oldest (first synced)
        // and mark the rest for deletion.
        $duplicateIds = [];

        PlayRecord::query()
            ->select('id', 'statsfm_connection_id', 'track_id', 'played_at')
            ->orderBy('statsfm_connection_id')
            ->orderBy('track_id')
            ->orderBy('played_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->statsfm_connection_id . '|' . $row->track_id . '|' . $row->played_at)
            ->each(function ($rows) use (&$duplicateIds) {
                if ($rows->count() > 1) {
                    // keep the first (oldest id), delete the rest
                    $duplicateIds = array_merge($duplicateIds, $rows->slice(1)->pluck('id')->all());
                }
            });

        if (empty($duplicateIds)) {
            $this->info('No duplicate play records found.');
            return self::SUCCESS;
        }

        $this->info(count($duplicateIds) . ' duplicate play record(s) found.');

        if ($dryRun) {
            $this->line('Dry run — nothing deleted. Row ids that would be removed:');
            $this->line(implode(', ', $duplicateIds));
            return self::SUCCESS;
        }

        DB::table('play_records')->whereIn('id', $duplicateIds)->delete();
        $this->info('Deleted ' . count($duplicateIds) . ' duplicate row(s).');

        return self::SUCCESS;
    }
}
