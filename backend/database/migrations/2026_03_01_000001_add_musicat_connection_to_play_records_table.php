<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_records', function (Blueprint $table) {
            // A play now comes from exactly one of two connection types:
            // Stats.fm (Spotify) or Musicat (Apple Music) — never both, and
            // application code (MusicatPlayRecordSyncer / PlayRecordSyncer)
            // always sets exactly one of these two columns per row.
            $table->foreignId('musicat_connection_id')
                ->nullable()
                ->after('statsfm_connection_id')
                ->constrained('musicat_connections')
                ->cascadeOnDelete();

            $table->index(['musicat_connection_id', 'played_at']);
        });

        // statsfm_connection_id was NOT NULL before; plays synced via
        // Musicat now leave it null, so it has to become nullable.
        Schema::table('play_records', function (Blueprint $table) {
            $table->foreignId('statsfm_connection_id')->nullable()->change();
        });

        // Musicat's own stream id also needs to be de-duplicated on, the
        // same way statsfm_stream_id is. Nullable + unique so a Musicat
        // play never collides with a Stats.fm play's id space.
        Schema::table('play_records', function (Blueprint $table) {
            $table->string('musicat_stream_id')->nullable()->unique()->after('statsfm_stream_id');
        });

        // statsfm_stream_id was globally unique + required; Musicat-sourced
        // rows don't have one, so relax it to nullable (uniqueness among
        // non-null values is preserved by the existing unique index).
        Schema::table('play_records', function (Blueprint $table) {
            $table->string('statsfm_stream_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('play_records', function (Blueprint $table) {
            $table->dropForeign(['musicat_connection_id']);
            $table->dropIndex(['musicat_connection_id', 'played_at']);
            $table->dropColumn(['musicat_connection_id', 'musicat_stream_id']);
        });
    }
};
