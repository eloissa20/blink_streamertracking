<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // The address as the user typed it (used to send/display the
            // mail) and its canonical form (dots/+tag/case stripped — see
            // GmailAddress::canonicalize) used for uniqueness. Only one
            // pending registration may exist per real Gmail inbox at a
            // time; starting a new one for the same inbox reuses/replaces
            // this row rather than creating a second one.
            $table->string('email');
            $table->string('email_canonical')->unique();

            // Already-hashed — a pending registration holds exactly what
            // a completed one would, just gated behind OTP verification
            // before it's promoted into a real `users` row.
            $table->string('password');

            // The OTP itself is never stored in plaintext, only its hash
            // (verified the same way a password is, via Hash::check) —
            // so a leak of this table alone doesn't hand out live codes.
            $table->string('otp_hash');
            $table->timestamp('otp_expires_at');

            // Wrong-code guesses against the *current* otp_hash. Reset to
            // 0 every time a new code is issued (register/resend).
            $table->unsignedTinyInteger('otp_attempts')->default(0);

            // How many times a code has been (re)sent for this pending
            // registration, and when the last one went out — enforces
            // both the total resend cap and the per-send cooldown.
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
