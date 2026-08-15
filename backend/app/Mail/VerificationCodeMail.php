<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Deliberately NOT ShouldQueue: this app has no queue worker running by
// default (QUEUE_CONNECTION=database with nothing consuming it), and a
// queued OTP mail that never gets picked up is a silent failure the user
// has no way to recover from except waiting forever. Sending synchronously
// costs a bit of request latency but guarantees the code either goes out
// as part of this request or the request itself fails loudly.
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $code  The plaintext OTP. Only ever held in memory
     *                        for the duration of building/sending this one
     *                        mail — never persisted; the DB only ever
     *                        stores its hash (see PendingRegistration).
     */
    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your verification code')
            ->view('emails.verification-code')
            ->with([
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
            ]);
    }
}
