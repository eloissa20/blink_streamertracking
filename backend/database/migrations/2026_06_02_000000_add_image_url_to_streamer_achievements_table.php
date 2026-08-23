<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_achievements', function (Blueprint $table) {
            // Snapshotted at unlock time, same as artist_name/song_title —
            // artist_image_url for an artist/solo-overall counter,
            // artwork_url for a song counter. Nullable because older rows
            // (unlocked before this column existed) and any play synced
            // before artwork metadata was available won't have one; the
            // card falls back to a generic icon in that case, never a
            // photo it made up itself.
            $table->string('image_url')->nullable()->after('song_title');
        });
    }

    public function down(): void
    {
        Schema::table('streamer_achievements', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
