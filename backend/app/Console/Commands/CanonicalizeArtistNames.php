<?php

namespace App\Console\Commands;

use App\Models\PlayRecord;
use App\Support\AllowedArtists;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites artist_name to its canonical spelling (e.g. "LiSA" -> "LISA")
 * for any row whose name matches an AllowedArtists entry case-insensitively
 * but isn't spelled exactly like it.
 *
 * WHY THE OLD VERSION CRASHED: play_records_conn_track_played_unique is
 * (connection_id, track_id, played_at) — it does NOT include artist_name.
 * If the same stream was ever synced twice under two different spellings
 * (e.g. once as "LiSA", once as "LISA") for the same connection/track/
 * moment, a plain UPDATE ... SET artist_name = 'LISA' on the "LiSA" row
 * collides with the row that's already "LISA" there. This version checks
 * for that collision before renaming: if a canonical duplicate already
 * occupies that exact (connection, track, played_at) slot, the
 * non-canonical row is deleted instead of renamed. Otherwise it's a plain
 * rename, same as before.
 */
class CanonicalizeArtistNames extends Command
{
    protected $signature = 'playrecords:canonicalize-artists {--dry-run : Show what would change without changing anything}';
    protected $description = 'Rewrite artist_name values to their canonical AllowedArtists spelling';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $canonicalByUpper = [];
        foreach (AllowedArtists::NAMES as $canonical) {
            $canonicalByUpper[strtoupper($canonical)] = $canonical;
        }

        $rows = PlayRecord::query()
            ->select('id', 'statsfm_connection_id', 'musicat_connection_id', 'track_id', 'played_at', 'artist_name')
            ->get();

        // Group non-canonical rows by their *current* spelling, purely for
        // the summary output ("Would update N row(s): "LiSA" -> LISA").
        $updates = [];   // ['LiSA' => ['canonical' => 'LISA', 'ids' => [...]]]
        $deletes = [];   // ['LiSA' => ['canonical' => 'LISA', 'ids' => [...]]]

        foreach ($rows as $row) {
            $upper = strtoupper(trim($row->artist_name));
            $canonical = $canonicalByUpper[$upper] ?? null;

            if ($canonical === null || $canonical === $row->artist_name) {
                continue; // not a recognized artist, or already canonical
            }

            $connColumn = $row->musicat_connection_id ? 'musicat_connection_id' : 'statsfm_connection_id';
            $connId = $row->musicat_connection_id ?? $row->statsfm_connection_id;

            if ($connId === null) {
                continue; // no connection to key off of — leave it alone
            }

            $collides = PlayRecord::query()
                ->where('id', '!=', $row->id)
                ->where($connColumn, $connId)
                ->where('track_id', $row->track_id)
                ->where('played_at', $row->played_at)
                ->where('artist_name', $canonical)
                ->exists();

            $bucket = $collides ? 'deletes' : 'updates';
            ${$bucket}[$row->artist_name]['canonical'] = $canonical;
            ${$bucket}[$row->artist_name]['ids'][] = $row->id;
        }

        if (empty($updates) && empty($deletes)) {
            $this->info('Nothing to canonicalize.');
            return self::SUCCESS;
        }

        foreach ($updates as $spelling => $group) {
            $count = count($group['ids']);
            $this->info("Would update {$count} row(s): \"{$spelling}\" -> {$group['canonical']} ({$count} rows)");
        }

        foreach ($deletes as $spelling => $group) {
            $count = count($group['ids']);
            $this->warn("Would delete {$count} row(s): \"{$spelling}\" duplicates an existing \"{$group['canonical']}\" row for the same connection/track/played_at ({$count} rows)");
        }

        if ($dryRun) {
            $this->line('Dry run — nothing changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates, $deletes) {
            foreach ($updates as $group) {
                PlayRecord::whereIn('id', $group['ids'])->update(['artist_name' => $group['canonical']]);
            }

            foreach ($deletes as $group) {
                PlayRecord::whereIn('id', $group['ids'])->delete();
            }
        });

        $updatedTotal = array_sum(array_map(fn ($g) => count($g['ids']), $updates));
        $deletedTotal = array_sum(array_map(fn ($g) => count($g['ids']), $deletes));

        $this->info("Updated {$updatedTotal} row(s), deleted {$deletedTotal} duplicate row(s).");

        return self::SUCCESS;
    }
}