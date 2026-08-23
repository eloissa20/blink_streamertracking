<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaming_missions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // A mission targets either one specific song (track_id set)
            // or an artist/member's overall total (track_id null,
            // artist_name set). Exactly one of the two should be filled
            // in — enforced in the model/controller rather than the DB,
            // since Laravel doesn't have a portable "exactly one of"
            // constraint.
            $table->string('track_id')->nullable();
            $table->string('track_name')->nullable();
            $table->string('artist_name');
            $table->string('artwork_url')->nullable();

            // e.g. 10,000 streams for one song, or 500,000 for a member's
            // overall total — counted across every user in the app, not
            // just the viewer.
            $table->unsignedBigInteger('target_streams');

            // Which color theme (see frontend lib/artistThemes.js) the
            // mission card should use; kept as a plain key rather than a
            // foreign key since themes live in frontend code, not the DB.
            $table->string('theme_key')->default('blackpink');

            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaming_missions');
    }
};
