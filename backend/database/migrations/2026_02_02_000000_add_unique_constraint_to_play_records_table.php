<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove any duplicate plays (same connection + track + played_at)
        // already sitting in the table, keeping the oldest row of each
        // group — otherwise the unique index below would fail to create.
        $duplicateIds = [];

        DB::table('play_records')
            ->select('id', 'statsfm_connection_id', 'track_id', 'played_at')
            ->orderBy('statsfm_connection_id')
            ->orderBy('track_id')
            ->orderBy('played_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->statsfm_connection_id.'|'.$row->track_id.'|'.$row->played_at)
            ->each(function ($rows) use (&$duplicateIds) {
                if ($rows->count() > 1) {
                    $duplicateIds = array_merge($duplicateIds, $rows->slice(1)->pluck('id')->all());
                }
            });

        if (! empty($duplicateIds)) {
            DB::table('play_records')->whereIn('id', $duplicateIds)->delete();
        }

        // From here on, the database itself refuses a second row for the
        // same connection + track + moment — the sync process can no
        // longer produce a duplicate no matter what id Stats.fm gives it.
        Schema::table('play_records', function (Blueprint $table) {
            $table->unique(
                ['statsfm_connection_id', 'track_id', 'played_at'],
                'play_records_conn_track_played_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('play_records', function (Blueprint $table) {
            $table->dropUnique('play_records_conn_track_played_unique');
        });
    }
};
