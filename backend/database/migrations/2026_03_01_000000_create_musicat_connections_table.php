<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('musicat_connections', function (Blueprint $table) {
            $table->id();

            // One Musicat connection per user, mirroring the Stats.fm
            // connection model. This is the exclusive source for Apple
            // Music data — Stats.fm connections are Spotify-only now (see
            // 2026_03_01_000001_restrict_statsfm_connections_to_spotify).
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('musicat_user_id');   // the Musicat platform user id
            $table->string('musicat_username');  // public Musicat handle, e.g. "shamara"
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();

            // Whether this user opts their listening data into the public
            // "Philippines Stream Overview" aggregate — same semantics as
            // statsfm_connections.include_in_public_overview.
            $table->boolean('include_in_public_overview')->default(true);

            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // A Musicat account can only be claimed by one local user.
            $table->unique('musicat_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musicat_connections');
    }
};
