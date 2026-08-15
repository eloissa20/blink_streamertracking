<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'email_canonical',
        'password',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'resend_count',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function isOtpExpired(): bool
    {
        return Carbon::now()->greaterThanOrEqualTo($this->otp_expires_at);
    }

    public function hasExceededVerifyAttempts(): bool
    {
        return $this->otp_attempts >= (int) config('registration.max_verify_attempts');
    }

    public function hasExceededResends(): bool
    {
        return $this->resend_count >= (int) config('registration.max_resends');
    }

    /**
     * Seconds until another resend is allowed, or 0 if one is allowed
     * right now. Used both to reject an early resend server-side and to
     * tell the client how long to disable the "resend" button for.
     */
    public function resendCooldownRemaining(): int
    {
        if (! $this->last_sent_at) {
            return 0;
        }

        $cooldown = (int) config('registration.resend_cooldown_seconds');

        // Raw timestamp subtraction, not diffInSeconds(): Carbon 3
        // (used by Laravel 12) changed diffInSeconds() to return a
        // *signed* difference by default, so Carbon::now()->diffInSeconds
        // ($this->last_sent_at) came back negative here (last_sent_at is
        // in the past relative to now) — and more negative the longer
        // you waited, which made "seconds remaining" grow instead of
        // count down. getTimestamp() subtraction has no such ambiguity.
        $elapsed = Carbon::now()->getTimestamp() - $this->last_sent_at->getTimestamp();

        return max(0, $cooldown - $elapsed);
    }

    public function checkOtp(string $candidate): bool
    {
        return Hash::check($candidate, $this->otp_hash);
    }
}