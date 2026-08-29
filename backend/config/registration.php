<?php

return [
    // Only addresses on this domain are accepted at registration. Kept
    // configurable rather than hardcoded in GmailAddress so an environment
    // can never accidentally widen this without an explicit env change.
    'allowed_domain' => env('REGISTRATION_ALLOWED_DOMAIN', 'gmail.com'),

    // How many digits the OTP has. 6 matches what most users already
    // expect from bank/SMS-style codes.
    'otp_length' => (int) env('REGISTRATION_OTP_LENGTH', 6),

    // How long a generated code stays valid. Kept short per the "5-10
    // minutes" requirement — a long-lived code sitting in an inbox is a
    // bigger window for it to leak or be reused.
    'otp_ttl_minutes' => (int) env('REGISTRATION_OTP_TTL_MINUTES', 10),

    // A pending registration is abandoned (and its email/name/password
    // discarded) if it's never verified within this window. Cleaned up by
    // PendingRegistration::scopeExpired() consumers (e.g. a scheduled
    // prune) rather than kept around indefinitely.
    'pending_ttl_hours' => (int) env('REGISTRATION_PENDING_TTL_HOURS', 24),

    // Wrong-code attempts allowed against a single OTP before the pending
    // registration is locked and the user must request a fresh code. Low
    // on purpose — a 6-digit code only has 1,000,000 possibilities, so
    // unlimited guesses would make brute-forcing it practical.
    'max_verify_attempts' => (int) env('REGISTRATION_MAX_VERIFY_ATTEMPTS', 5),

    // Resends allowed for a single pending registration before we stop
    // sending more mail for it, to keep someone from using this endpoint
    // to spam an inbox (their own or someone else's) or hammer the mail
    // provider.
    'max_resends' => (int) env('REGISTRATION_MAX_RESENDS', 5),

    'skip_email_delivery' => env('SKIP_EMAIL_DELIVERY', false),

    // Minimum gap between two sends (initial send counts as one) for the
    // same pending registration, regardless of the resend-count cap above.
    'resend_cooldown_seconds' => (int) env('REGISTRATION_RESEND_COOLDOWN_SECONDS', 60),
];
