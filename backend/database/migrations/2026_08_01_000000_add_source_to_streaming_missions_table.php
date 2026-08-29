<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which platform a mission's progress is counted from: 'spotify' or
     * 'apple_music' (matches PlayRecord::source exactly, so
     * StreamingMission::matchingPlaysQuery() can filter on it directly).
     *
     * Nullable — and left null — for any pre-existing mission row, which
     * keeps counting BOTH platforms combined exactly as it did before this
     * migration (see matchingPlaysQuery()'s null-check). Every mission the
     * seeder manages going forward always sets this explicitly, so null
     * only ever shows up on rows this migration doesn't touch.
     */
    public function up(): void
    {
        Schema::table('streaming_missions', function (Blueprint $table) {
            $table->string('source')->nullable()->after('theme_key');
        });
    }

    public function down(): void
    {
        Schema::table('streaming_missions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
