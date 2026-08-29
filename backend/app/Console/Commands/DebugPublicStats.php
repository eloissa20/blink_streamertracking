<?php

namespace App\Console\Commands;

use App\Models\MusicatConnection;
use App\Models\PlayRecord;
use App\Models\StatsFmConnection;
use Illuminate\Console\Command;

/**
 * Diagnostic only. Public Stats (PublicDashboardController) applies THREE
 * filters on top of what My Stats (PersonalStatsController) applies:
 *
 *   1. The playing user's connection must have include_in_public_overview
 *      = true (My Stats has no such requirement — it's your own data).
 *   2. Top Tracks/Top Artists still require ->allowedArtists() (same as
 *      My Stats).
 *   3. ->matchingConnectedSource() must pass (same as My Stats).
 *
 * Since My Stats already shows a track, (2) and (3) are almost certainly
 * fine for it — this walks (1) explicitly, then re-runs the exact same
 * query PublicDashboardController::baseQuery() runs, so you can see
 * exactly which stage a given artist/track drops out at instead of
 * guessing.
 *
 * Usage: php artisan stats:debug-public jennie apple_music
 *        php artisan stats:debug-public jennie apple_music --track="Heaven"
 */
class DebugPublicStats extends Command
{
    protected $signature = 'stats:debug-public {artist} {source=apple_music} {--track=}';
    protected $description = 'Diagnose why an artist/track is missing from the public dashboard';

    public function handle(): int
    {
        $artist = $this->argument('artist');
        $source = $this->argument('source');
        $track = $this->option('track');

        $this->info("Checking artist \"{$artist}\" / source \"{$source}\"".($track ? " / track \"{$track}\"" : '').'...');
        $this->newLine();

        // Step 1: does ANY play_records row exist at all for this
        // artist+source, regardless of every other filter?
        $rawQuery = PlayRecord::query()->where('source', $source)
            ->whereRaw('UPPER(artist_name) LIKE ?', ['%'.mb_strtoupper($artist).'%']);
        if ($track) {
            $rawQuery->whereRaw('UPPER(track_name) LIKE ?', ['%'.mb_strtoupper($track).'%']);
        }
        $raw = $rawQuery->get(['id', 'user_id', 'artist_name', 'track_name', 'source', 'musicat_connection_id', 'statsfm_connection_id', 'played_at']);

        $this->line("Step 1 — raw play_records rows matching (no filters at all): {$raw->count()}");
        foreach ($raw as $row) {
            $this->line("  id={$row->id} user_id={$row->user_id} artist=\"{$row->artist_name}\" track=\"{$row->track_name}\" musicat_connection_id=".($row->musicat_connection_id ?? 'NULL')." statsfm_connection_id=".($row->statsfm_connection_id ?? 'NULL')." played_at={$row->played_at}");
        }
        if ($raw->isEmpty()) {
            $this->error('No matching rows in play_records at all — this is a SYNC problem (the play never got stored), not a public-dashboard filter problem. Check that "My Stats" actually shows this track for this same connection.');
            return self::FAILURE;
        }
        $this->newLine();

        // Step 2: does the OWNING user's connection have
        // include_in_public_overview = true? This is the filter My Stats
        // never applies, so it's the most likely place for a track that
        // shows on My Stats but not Public Stats to disappear.
        $userIds = $raw->pluck('user_id')->unique();
        $this->line('Step 2 — include_in_public_overview flag for each owning user:');
        foreach ($userIds as $userId) {
            $statsFm = StatsFmConnection::where('user_id', $userId)->get(['id', 'include_in_public_overview', 'connected_source']);
            $musicat = MusicatConnection::where('user_id', $userId)->get(['id', 'include_in_public_overview']);

            foreach ($statsFm as $c) {
                $this->line("  user_id={$userId} StatsFmConnection#{$c->id} include_in_public_overview=".($c->include_in_public_overview ? 'true' : 'FALSE').' connected_source='.($c->connected_source ?? 'null'));
            }
            foreach ($musicat as $c) {
                $this->line("  user_id={$userId} MusicatConnection#{$c->id} include_in_public_overview=".($c->include_in_public_overview ? 'true' : 'FALSE'));
            }
            if ($statsFm->isEmpty() && $musicat->isEmpty()) {
                $this->error("  user_id={$userId} has NO connection rows at all (orphaned play_records?)");
            }
        }
        $optedInUserIds = StatsFmConnection::where('include_in_public_overview', true)->pluck('user_id')
            ->merge(MusicatConnection::where('include_in_public_overview', true)->pluck('user_id'))
            ->unique();
        $anyOptedIn = $userIds->intersect($optedInUserIds)->isNotEmpty();
        if (! $anyOptedIn) {
            $this->error('None of the owning user(s) above are opted into the public overview — this is exactly why it\'s missing from Public Stats. Set include_in_public_overview = true on that connection to fix it.');
        } else {
            $this->info('At least one owning user IS opted in — filter (1) passes.');
        }
        $this->newLine();

        // Step 3: run the exact same scopes PublicDashboardController
        // applies, to see if allowedArtists()/matchingConnectedSource()
        // is what's actually excluding it instead.
        $fullQuery = PlayRecord::query()
            ->whereIn('user_id', $optedInUserIds)
            ->where('source', $source)
            ->whereRaw('UPPER(artist_name) LIKE ?', ['%'.mb_strtoupper($artist).'%'])
            ->allowedArtists()
            ->matchingConnectedSource();
        if ($track) {
            $fullQuery->whereRaw('UPPER(track_name) LIKE ?', ['%'.mb_strtoupper($track).'%']);
        }
        $finalCount = $fullQuery->count();
        $this->line("Step 3 — rows surviving the FULL public-dashboard query (opted-in + allowedArtists + matchingConnectedSource): {$finalCount}");

        if ($finalCount === 0) {
            if ($anyOptedIn) {
                $this->error('Opted in, but still excluded — check allowedArtists()/matchingConnectedSource() rules (e.g. musicat_connection_id pointing at a deleted connection).');
            }
            return self::SUCCESS;
        }

        $this->newLine();

        // Step 4: Top Tracks/Top Artists are both LIMIT 25 and ordered by
        // stream_count DESC — a track/artist that passes every filter
        // above can still fail to ever show up simply because 25 OTHER
        // tracks/artists already have more plays. This computes the
        // live (uncached) leaderboard exactly the way
        // PublicDashboardController does, and reports this track/artist's
        // actual rank and stream_count, so "it's correctly outside the
        // top 25" and "this is a real bug" are no longer indistinguishable.
        $optedInBase = fn () => PlayRecord::query()
            ->whereIn('user_id', $optedInUserIds)
            ->where('source', $source)
            ->allowedArtists()
            ->matchingConnectedSource();

        if ($track) {
            $leaderboard = $optedInBase()
                ->selectRaw('track_id, track_name, artist_name, COUNT(*) as stream_count')
                ->groupBy('track_id', 'track_name', 'artist_name')
                ->orderByDesc('stream_count')
                ->get();

            $this->line("Step 4 — live Top Tracks leaderboard for this platform ({$leaderboard->count()} distinct tracks total, only top 25 ever get shown):");
            $targetTrackUpper = mb_strtoupper($track);
            foreach ($leaderboard as $i => $row) {
                $isTarget = str_contains(mb_strtoupper($row->track_name), $targetTrackUpper);
                $marker = $isTarget ? '  <== THIS TRACK' : '';
                if ($i < 25 || $isTarget) {
                    $cutoff = $i === 25 ? "\n  ---- cutoff: only rows above this line are shown on the public page ----" : '';
                    $this->line('  #'.($i + 1)." \"{$row->track_name}\" by {$row->artist_name} — {$row->stream_count} streams{$marker}{$cutoff}");
                }
            }
        } else {
            $leaderboard = $optedInBase()
                ->selectRaw('artist_id, artist_name, COUNT(*) as stream_count')
                ->groupBy('artist_id', 'artist_name')
                ->orderByDesc('stream_count')
                ->get();

            $this->line("Step 4 — live Top Artists leaderboard for this platform ({$leaderboard->count()} distinct artists total — the allow-list only has 5, so this should basically always be everyone):");
            foreach ($leaderboard as $i => $row) {
                $isTarget = str_contains(mb_strtoupper($row->artist_name), mb_strtoupper($artist));
                $marker = $isTarget ? '  <== THIS ARTIST' : '';
                $this->line('  #'.($i + 1)." {$row->artist_name} — {$row->stream_count} streams{$marker}");
            }
        }
        $this->newLine();

        $this->info('This artist/track SHOULD be appearing on Public Stats. If it still isn\'t, check: is it ranked outside the top 25 (see Step 4 above), or is the 60s response cache stale (run `php artisan cache:clear`)?');

        return self::SUCCESS;
    }
}
