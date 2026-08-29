<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stable, human-readable identifier for a mission (e.g.
     * "blackpink-jump") separate from its title. Titles are free-text and
     * can be edited without changing what the mission "is"; the slug is
     * what the seeder matches on so re-running it updates an existing
     * mission in place instead of creating a duplicate every time the
     * title copy changes.
     */
    public function up(): void
    {
        Schema::table('streaming_missions', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('streaming_missions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
