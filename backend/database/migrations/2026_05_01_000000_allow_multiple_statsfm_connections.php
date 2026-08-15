<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lifts the original "one Stats.fm connection per user" constraint so
     * a single system account can link multiple Spotify accounts (via
     * multiple Stats.fm connections) at once, each with fully isolated
     * plays/stats — see StatsFmController and
     * PlayRecord::scopeForStatsFmConnection().
     *
     * The `statsfm_user_id` unique constraint is intentionally left in
     * place: a given real Stats.fm/Spotify account should still only ever
     * be linked to one system user, just not the other way around.
     *
     * MySQL/MariaDB refuse to drop a unique index while a foreign key
     * still depends on it ("Cannot drop index ... needed in a foreign
     * key constraint"), which is what the original single Schema::table
     * call hit. The fix is to drop the foreign key first, then the
     * unique index, then recreate the foreign key against a plain
     * (non-unique) index — done as three separate Schema::table calls
     * since some drivers apply everything inside one call as a single
     * ALTER TABLE statement, which would hit the same ordering problem
     * all over again if it were left in one block.
     */
    public function up(): void
    {
        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('statsfm_connections', function (Blueprint $table) {
            // Re-adding the foreign key here also creates the plain
            // (non-unique) index MySQL requires a FK column to have —
            // no separate ->index('user_id') call needed, and adding one
            // anyway risks a "duplicate key name" error against the
            // index the foreign key just created.
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Optional user-chosen nickname (e.g. "Main", "Alt / Gaming")
            // to tell connections apart in the UI once there's more than
            // one — falls back to statsfm_username when not set.
            $table->string('label')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->dropColumn('label');
            $table->dropForeign(['user_id']);
        });

        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};