<?php

namespace App\Support;

class GmailAddress
{
    /**
     * Gmail's own published rules for a *local part* (the bit before the
     * @) are stricter than general RFC 5321/5322 email syntax: 6-30
     * characters (dots don't count toward that), letters/digits/dots
     * only, must start with a letter, and no leading/trailing/consecutive
     * dots. A "+tag" suffix (e.g. "jane.doe+news") is allowed and is
     * validated separately below, not as part of this pattern.
     *
     * Checking against these — not just "is this a syntactically valid
     * email" — is what lets us reject addresses that are the right shape
     * to be *some* email but couldn't possibly be a real Gmail inbox
     * (e.g. "1234@gmail.com", "a..b@gmail.com", "_private@gmail.com").
     */
    private const LOCAL_PART_PATTERN = '/^[a-z][a-z0-9]*(\.[a-z0-9]+)*$/i';

    private const MIN_LOCAL_LENGTH = 6;
    private const MAX_LOCAL_LENGTH = 30;

    /**
     * True if $email is both a syntactically valid address and a
     * plausible real Gmail address: exactly the configured domain (no
     * lookalikes, no subdomain tricks), and a local part that matches the
     * rules Gmail itself enforces when an account is created.
     *
     * Deliberately does NOT perform a DNS/MX lookup or attempt to contact
     * Google — this environment may not have outbound network access,
     * and a structural check already rejects the overwhelming majority of
     * disposable/fake addresses without depending on an external call
     * that can time out or be unavailable. The OTP step that follows is
     * the real proof the inbox exists and is reachable by the person
     * registering.
     */
    public static function isValid(?string $email): bool
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // filter_var() already rejected anything without exactly one "@",
        // so this split is safe.
        [$local, $domain] = explode('@', $email, 2);

        if (! self::isAllowedDomain($domain)) {
            return false;
        }

        return self::isValidLocalPart($local);
    }

    /**
     * Case-insensitive, exact match against the configured domain
     * (default "gmail.com") — not a "contains" or "ends with" check, so
     * neither a disposable service at a lookalike domain (e.g.
     * "gmail.com.tempmail.net") nor a sibling Google domain (e.g.
     * "googlemail.com") slips through. Anything that isn't gmail.com
     * itself is rejected, which is what "reject any non-Gmail emails"
     * and "reject disposable/temporary email services" both reduce to
     * once we're only ever accepting one exact domain.
     */
    private static function isAllowedDomain(string $domain): bool
    {
        $allowed = strtolower(trim((string) config('registration.allowed_domain', 'gmail.com')));

        return strtolower(trim($domain)) === $allowed;
    }

    private static function isValidLocalPart(string $local): bool
    {
        // Split off a "+tag" suffix first, if present, and validate it
        // separately — Gmail allows arbitrary characters after the "+"
        // (people use it for filters, e.g. "+shopping", "+2024"), so it
        // shouldn't be run through the base-username pattern below, but
        // it also can't be empty ("user+@gmail.com" isn't a real Gmail
        // address) or contain another "@"/whitespace.
        $base = $local;
        if (str_contains($local, '+')) {
            [$base, $tag] = explode('+', $local, 2);
            if ($tag === '' || preg_match('/\s/', $tag)) {
                return false;
            }
        }

        // Gmail ignores dots when counting length and when treating two
        // addresses as "the same" inbox, so length is checked on the
        // dot-stripped form.
        $withoutDots = str_replace('.', '', $base);
        $length = strlen($withoutDots);

        if ($length < self::MIN_LOCAL_LENGTH || $length > self::MAX_LOCAL_LENGTH) {
            return false;
        }

        return preg_match(self::LOCAL_PART_PATTERN, $base) === 1;
    }

    /**
     * Gmail treats dots in the local part as insignificant and ignores
     * anything from "+" onward, so "Jane.Doe+news@gmail.com",
     * "janedoe@gmail.com", and "JANEDOE@GMAIL.COM" are all the exact same
     * inbox. Without canonicalizing before we check "is this email
     * already registered", someone could register many separate accounts
     * against one real inbox just by varying dots/case/tag — trivially
     * defeating "only accept legitimate Gmail accounts" as a one-inbox-
     * one-account rule. This collapses an address to the single
     * lowercase, dot-stripped, tag-stripped form used for that
     * uniqueness check (users.email_canonical / pending_registrations.
     * email_canonical) — never for display, delivery, or storage as the
     * address itself.
     *
     * Assumes $email has already passed isValid(); behavior on a
     * non-Gmail or malformed address is undefined.
     */
    public static function canonicalize(string $email): string
    {
        [$local, $domain] = explode('@', strtolower(trim($email)), 2);

        $local = explode('+', $local, 2)[0];
        $local = str_replace('.', '', $local);

        return "{$local}@{$domain}";
    }
}
