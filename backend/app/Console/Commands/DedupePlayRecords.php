<?php

namespace App\Console\Commands;

use App\Models\PlayRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupePlayRecords extends Command
{
    protected $signature = 'playrecords:dedupe {--dry-run : Show what would be deleted without deleting anything}';
    protected $description = 'Remove duplicate play records — same connection (Stats.fm or Musicat) + track, played within a few minutes of each other';

    /**
     * Two rows for the same connection + track within this many minutes of
     * each other are treated as the same real play, not two plays. Exact
     * equality on played_at isn't safe to rely on: a Musicat-sourced row's
     * timestamp is scraped off a rendered page and can drift a little
     * between sync runs (see MusicatService::reconcilePlayedAt), so an
     * exact-match-only comparison misses those near-duplicates entirely.
     */
    private const DUPLICATE_WINDOW_MINUTES = 5;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Group by whichever connection actually backs the row — a row is
        // either Stats.fm-sourced (statsfm_connection_id) or
        // Musicat-sourced (musicat_connection_id), never both. Grouping on
        // statsfm_connection_id alone, as this command used to, silently
        // ignores every Musicat/Apple Music row: they all have a null
        // statsfm_connection_id, so they'd never be recognized as
        // duplicates of one another no matter how many times the same
        // play got synced in.
        $duplicateIds = [];

        PlayRecord::query()
            ->select('id', 'statsfm_connection_id', 'musicat_connection_id', 'track_id', 'played_at')
            ->orderByRaw('COALESCE(statsfm_connection_id, 0)')
            ->orderByRaw('COALESCE(musicat_connection_id, 0)')
            ->orderBy('track_id')
            ->orderBy('played_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => ($row->statsfm_connection_id ? "sfm:{$row->statsfm_connection_id}" : "musicat:{$row->musicat_connection_id}")
                . '|' . $row->track_id)
            ->each(function ($rows) use (&$duplicateIds) {
                // Within a connection+track group, rows are already sorted
                // by played_at. Walk through and cluster anything within
                // DUPLICATE_WINDOW_MINUTES of the anchor for the current
                // cluster into that cluster; keep only the earliest
                // (first synced) row per cluster and mark the rest as
                // duplicates. A gap larger than the window starts a new
                // cluster, so two genuinely separate plays of the same
                // song hours apart are correctly left alone.
                $anchor = null;

                foreach ($rows as $row) {
                    if ($anchor === null) {
                        $anchor = $row;
                        continue;
                    }

                    $minutesFromAnchor = abs(
                        \Illuminate\Support\Carbon::parse($row->played_at)
                            ->diffInMinutes(\Illuminate\Support\Carbon::parse($anchor->played_at))
                    );

                    if ($minutesFromAnchor <= self::DUPLICATE_WINDOW_MINUTES) {
                        $duplicateIds[] = $row->id;
                    } else {
                        $anchor = $row;
                    }
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
