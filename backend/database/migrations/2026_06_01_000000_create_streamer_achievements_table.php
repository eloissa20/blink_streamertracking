<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streamer_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Stable identifier for the thing being leveled, e.g.
            // "artist:blackpink" for the group total, or "song:3n2VfL..."
            // (the track_id) for a single song/solo work. Kept as a plain
            // string rather than separate artist/track columns so the
            // same table covers both counter types without nullable
            // foreign keys.
            $table->string('achievement_key');
            $table->enum('type', ['artist', 'solo']);

            // Denormalized display info, snapshotted at unlock time so
            // the achievement still reads sensibly even if track/artist
            // metadata changes later.
            $table->string('artist_name')->nullable();
            $table->string('member_name')->nullable();
            $table->string('song_title')->nullable();

            $table->unsignedInteger('level');
            $table->string('tier');
            $table->unsignedBigInteger('total_streams_at_unlock');

            $table->timestamp('achieved_at');
            $table->timestamps();

            // A user can only unlock a given level of a given counter
            // once — this is what makes the "record the achievement"
            // endpoint safely callable on every page load without ever
            // creating duplicates.
            $table->unique(['user_id', 'achievement_key', 'level'], 'streamer_achievements_user_key_level_unique');
            $table->index(['user_id', 'achievement_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streamer_achievements');
    }
};
