<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statsfm_connections', function (Blueprint $table) {
            $table->id();

            // One connection per user (enforced unique) — a user may only ever
            // link a single Stats.fm account, covering both Spotify + Apple Music.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('statsfm_user_id');   // the Stats.fm platform user id
            $table->string('statsfm_username');  // public Stats.fm handle, e.g. "juan.dc"
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();

            // Whether this user opts their listening data into the public
            // "Philippines Stream Overview" aggregate.
            $table->boolean('include_in_public_overview')->default(true);

            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Stats.fm accounts themselves are also unique per app user — the
            // same Stats.fm account cannot be linked to two different local users.
            $table->unique('statsfm_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statsfm_connections');
    }
};
