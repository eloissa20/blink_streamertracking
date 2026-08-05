<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_records', function (Blueprint $table) {
            $table->string('artist_image_url')->nullable()->after('artwork_url');
        });
    }

    public function down(): void
    {
        Schema::table('play_records', function (Blueprint $table) {
            $table->dropColumn('artist_image_url');
        });
    }
};
