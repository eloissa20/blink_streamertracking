<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statsfm_connections', function (Blueprint $table) {
            // Which single service this connection tracks. Required for new
            // connections going forward (enforced in StatsFmController), so
            // a user is never counting both Spotify and Apple Music plays
            // through the same connection. Nullable only so any pre-existing
            // connection rows (created before this feature existed) don't
            // break — they're treated as unrestricted until the user
            // reconnects and picks one.
            $table->string('connected_source')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('statsfm_connections', function (Blueprint $table) {
            $table->dropColumn('connected_source');
        });
    }
};
