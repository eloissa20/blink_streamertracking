<?php

namespace App\Console\Commands;

use App\Models\MusicatConnection;
use App\Models\PlayRecord;
use App\Services\MusicatService;
use Illuminate\Console\Command;

/**
 * One-time cleanup for rows that got artist_image_url = null because of
 * the case-sensitive lookup bug in MusicatPlayRecordSyncer (fixed
 * separately) — re-fetches each connection's current "Top artists" photo
 * map and fills in any play_records row that's missing an image but has
 * a case-insensitive match available now.
 *
 * Safe to run repeatedly; only ever touches rows where artist_image_url
 * IS NULL, and only writes when a match is actually found.
 */
class BackfillMusicatArtistImages extends Command
{
    protected $signature = 'musicat:backfill-artist-images {--dry-run : Show what would change without changing anything}';
    protected $description = 'Fill in artist_image_url for Musicat-sourced play_records rows that are missing one due to a case-mismatch on the old lookup';

    public function handle(MusicatService $musicat): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $connections = MusicatConnection::all();
        $totalUpdated = 0;

        foreach ($connections as $connection) {
            $missing = PlayRecord::query()
                ->where('musicat_connection_id', $connection->id)
                ->whereNull('artist_image_url')
                ->get(['id', 'artist_name']);

            if ($missing->isEmpty()) {
                continue;
            }

            $artistImages = [];
            foreach ($musicat->artistImageMap($connection->musicat_user_id) as $name => $url) {
                $artistImages[strtoupper(trim($name))] = $url;
            }

            if (empty($artistImages)) {
                $this->warn("  {$connection->musicat_username}: couldn't fetch a Top Artists photo map (page didn't render, or section empty) — skipping.");
                continue;
            }

            $updatedForConnection = 0;

            foreach ($missing as $row) {
                $key = strtoupper(trim($row->artist_name));
                $url = $artistImages[$key] ?? null;

                if ($url === null) {
                    continue; // still no match — e.g. artist genuinely isn't in Top Artists right now
                }

                $updatedForConnection++;

                if (! $dryRun) {
                    PlayRecord::whereId($row->id)->update(['artist_image_url' => $url]);
                }
            }

            $this->info("{$connection->musicat_username}: {$updatedForConnection} row(s) " . ($dryRun ? 'would be updated' : 'updated') . " (out of {$missing->count()} missing an image)");

            $totalUpdated += $updatedForConnection;
        }

        $this->info($dryRun ? "Dry run — {$totalUpdated} row(s) total would be updated." : "Done — {$totalUpdated} row(s) total updated.");

        return self::SUCCESS;
    }
}