<?php

use App\Support\GmailAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_canonical')->nullable()->after('email');
        });

        // Backfill any pre-existing rows. Gmail's canonicalization is
        // stripping-based (dots/+tag/case), not a reversible SQL
        // expression a portable migration can express as one UPDATE
        // across every DB driver this app might run on — looping in PHP
        // keeps it correct and driver-agnostic for what should be a
        // one-time, small table.
        DB::table('users')->select('id', 'email')->orderBy('id')->each(function ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['email_canonical' => GmailAddress::canonicalize($user->email)]);
        });

        // Split into two statements rather than chaining
        // ->nullable(false)->unique()->change() in one: Laravel's native
        // (doctrine/dbal-free) column alteration reliably handles
        // NOT NULL changes on its own, but adding a new unique index as
        // part of the same "modify column" statement isn't something to
        // rely on across drivers (this app runs MySQL in prod, SQLite in
        // some dev setups) — a plain ->unique() call is unambiguous on
        // both.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_canonical')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email_canonical');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email_canonical']);
            $table->dropColumn('email_canonical');
        });
    }
};
