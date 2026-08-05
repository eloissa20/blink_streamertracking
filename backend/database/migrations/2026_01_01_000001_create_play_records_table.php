<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statsfm_connection_id')->constrained()->cascadeOnDelete();

            // Stats.fm's own play/stream id, used to de-duplicate on re-sync.
            $table->string('statsfm_stream_id')->unique();

            $table->string('track_id');
            $table->string('track_name');
            $table->string('artist_id');
            $table->string('artist_name');
            $table->string('album_name')->nullable();
            $table->string('artwork_url')->nullable();

            $table->enum('source', ['spotify', 'apple_music'])->default('spotify');

            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('played_at');

            $table->timestamps();

            $table->index(['user_id', 'played_at']);
            $table->index(['artist_id', 'played_at']);
            $table->index(['track_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_records');
    }
};
