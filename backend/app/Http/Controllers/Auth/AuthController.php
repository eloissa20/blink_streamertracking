<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\GmailAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Step 1 of registration: validate the submitted details (name,
     * password, and — critically — that the email is a real-looking
     * Gmail address that isn't already registered), then email a one-
     * time code. No `users` row is created here; account creation only
     * happens once verifyRegistration() confirms the person actually
     * controls that inbox.
     *
     * Re-submitting the same (still-pending, unverified) Gmail address —
     * e.g. the user mistyped their password the first time — updates the
     * pending name/password and sends a fresh code, subject to the same
     * resend cooldown/cap as an explicit resendCode() call, rather than
     * creating a second row or resetting the count.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = trim((string) $request->input('email'));

        // Structural Gmail check — right domain, and a local part that
        // could plausibly be a real Gmail username. See GmailAddress for
        // why this alone is enough to reject non-Gmail and disposable-
        // service addresses without a network call.
        if (! GmailAddress::isValid($email)) {
            return response()->json([
                'errors' => [
                    'email' => ['Please register with a real Gmail address (name@gmail.com). Other providers and temporary/disposable email services are not accepted.'],
                ],
            ], 422);
        }

        $canonical = GmailAddress::canonicalize($email);

        if (User::where('email_canonical', $canonical)->exists()) {
            return response()->json([
                'errors' => ['email' => ['An account already exists for this Gmail address.']],
            ], 422);
        }

        $pending = PendingRegistration::where('email_canonical', $canonical)->first();

        if ($pending && ($cooldown = $pending->resendCooldownRemaining()) > 0) {
            return response()->json([
                'message' => "Please wait {$cooldown}s before requesting another code.",
                'retry_after_seconds' => $cooldown,
            ], 429);
        }

        if ($pending && $pending->hasExceededResends()) {
            return response()->json([
                'message' => 'Too many verification codes have been requested for this address. Please try again later.',
            ], 429);
        }

        $pending = $this->issueCode($pending, [
            'name' => (string) $request->input('name'),
            'email' => $email,
            'email_canonical' => $canonical,
            'password' => Hash::make((string) $request->input('password')),
        ]);

        return response()->json([
            'message' => 'A verification code has been sent to your Gmail address.',
            'email' => $pending->email,
            'expires_in_minutes' => (int) config('registration.otp_ttl_minutes'),
        ]);
    }

    /**
     * Step 2: the code the user got by email, checked against the
     * pending registration created in register(). Only on a correct,
     * unexpired code does a real `users` row (and therefore an account/
     * session) get created — everything before this point is provisional
     * and lives only in `pending_registrations`.
     */
    public function verifyRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $canonical = GmailAddress::canonicalize(trim((string) $request->input('email')));
        $pending = PendingRegistration::where('email_canonical', $canonical)->first();

        if (! $pending) {
            return response()->json([
                'message' => 'No pending registration found for this email. Please start registration again.',
            ], 404);
        }

        if ($pending->isOtpExpired()) {
            return response()->json([
                'message' => 'This code has expired. Please request a new one.',
            ], 410);
        }

        if ($pending->hasExceededVerifyAttempts()) {
            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ], 429);
        }

        if (! $pending->checkOtp((string) $request->input('code'))) {
            $pending->increment('otp_attempts');

            $remaining = max(0, (int) config('registration.max_verify_attempts') - $pending->otp_attempts);

            return response()->json([
                'errors' => ['code' => ['Incorrect code.']],
                'attempts_remaining' => $remaining,
            ], 422);
        }

        $user = DB::transaction(function () use ($pending) {
            $user = User::create([
                'name' => $pending->name,
                'email' => $pending->email,
                'email_canonical' => $pending->email_canonical,
                'password' => $pending->password, // already hashed
                'email_verified_at' => Carbon::now(),
            ]);

            // Clear out every pending row for this inbox (normally just
            // one), not merely this specific one, so nothing stale is
            // left behind that could later collide on the unique
            // email_canonical constraint.
            PendingRegistration::where('email_canonical', $pending->email_canonical)->delete();

            return $user;
        });

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Explicit "resend code" action, separate from register() so the
     * frontend can offer a plain resend button without re-submitting
     * name/password. Shares the same cooldown/cap enforcement.
     */
    public function resendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $canonical = GmailAddress::canonicalize(trim((string) $request->input('email')));
        $pending = PendingRegistration::where('email_canonical', $canonical)->first();

        if (! $pending) {
            return response()->json([
                'message' => 'No pending registration found for this email. Please start registration again.',
            ], 404);
        }

        if (($cooldown = $pending->resendCooldownRemaining()) > 0) {
            return response()->json([
                'message' => "Please wait {$cooldown}s before requesting another code.",
                'retry_after_seconds' => $cooldown,
            ], 429);
        }

        if ($pending->hasExceededResends()) {
            return response()->json([
                'message' => 'Too many verification codes have been requested for this address. Please try again later.',
            ], 429);
        }

        $pending = $this->issueCode($pending, []);

        return response()->json([
            'message' => 'A new verification code has been sent to your Gmail address.',
            'email' => $pending->email,
            'expires_in_minutes' => (int) config('registration.otp_ttl_minutes'),
        ]);
    }

    /**
     * Generate a fresh OTP, persist its hash (never the plaintext code)
     * onto $pending (creating it first if this is a brand new
     * registration attempt), bump the resend bookkeeping, and email it.
     * Shared by register() and resendCode() so both paths enforce the
     * exact same expiry/attempt-reset/resend-count behavior.
     */
    private function issueCode(?PendingRegistration $pending, array $attributes): PendingRegistration
    {
        $length = (int) config('registration.otp_length');
        $ttl = (int) config('registration.otp_ttl_minutes');
        $code = $this->generateOtp($length);
        $email = $attributes['email'] ?? $pending?->email;

        // Send first, persist second. If delivery throws, we bail out
        // (the controller lets this 500 rather than pretending it
        // succeeded) without having touched resend_count/otp_attempts/
        // otp_expires_at — so a transient mail-provider failure doesn't
        // burn one of the user's limited resend attempts or invalidate
        // a still-valid earlier code for no reason.
        Mail::to($email)->send(new VerificationCodeMail($code, $ttl));

        $fields = array_merge($attributes, [
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => Carbon::now()->addMinutes($ttl),
            'otp_attempts' => 0,
            'last_sent_at' => Carbon::now(),
        ]);

        if ($pending) {
            $fields['resend_count'] = $pending->resend_count + 1;
            $pending->fill($fields);
            $pending->save();
        } else {
            $fields['resend_count'] = 1;
            $pending = PendingRegistration::create($fields);
        }

        return $pending;
    }

    /**
     * Cryptographically random numeric code, generated digit-by-digit via
     * random_int() so it works for any configured length without risking
     * integer overflow (unlike random_int(0, 10**$length - 1) for larger
     * lengths) and without ever producing a short code padded with
     * leading zeros in a way that narrows the real keyspace.
     */
    private function generateOtp(int $length): string
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return $digits;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'has_connected_statsfm' => $request->user()->hasConnectedStatsFm(),
            'has_connected_musicat' => $request->user()->hasConnectedMusicat(),
        ]);
    }
}
