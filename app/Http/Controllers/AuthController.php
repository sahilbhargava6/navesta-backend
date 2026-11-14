<?php

namespace App\Http\Controllers;

use App\Contracts\MessageGateway;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConsentService;
use App\Services\FootprintService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Two ways to stay signed in:
 * - the website (a stateful origin, see SANCTUM_STATEFUL_DOMAINS) gets an
 *   HTTP-only session cookie, protected by CSRF; no token reaches JavaScript
 * - any other client (mobile app, scripts) gets a bearer token
 */
class AuthController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private OtpService $otp,
        private FootprintService $footprint,
    ) {}

    public function register(RegisterRequest $request, ConsentService $consents): JsonResponse
    {
        $data = $request->validated();
        $email = strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            abort(400, 'User already exists with this email address');
        }

        $remindersOptIn = (bool) ($data['acceptReminders'] ?? false);

        $user = DB::transaction(function () use ($data, $email, $remindersOptIn, $consents) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'phone' => isset($data['phone']) ? preg_replace('/\s+/', '', $data['phone']) : null,
                'password_hash' => Hash::make($data['password']),
                'property_status' => $data['propertyStatus'] ?? null,
                'employment_sector' => $data['employmentSector'] ?? null,
                'reminders_enabled' => $remindersOptIn,
            ]);

            $consents->record($user, 'terms', true);
            $consents->record($user, 'privacy', true);
            $consents->record($user, 'reminders', $remindersOptIn);

            return $user;
        });

        $this->footprint->capture($request, 'registration', $user);

        return $this->ok([
            'token' => $this->startSession($request, $user),
            'user' => [...$this->profile($user), 'createdAt' => $user->created_at],
        ], 'User registered successfully', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = strtolower($data['email']);
        $lockKey = 'login-failures:'.sha1($email);
        $maxFailures = (int) config('navesta.auth.max_failures');

        if (RateLimiter::tooManyAttempts($lockKey, $maxFailures)) {
            $minutes = (int) ceil(RateLimiter::availableIn($lockKey) / 60);
            $this->audit->record('auth.login.locked', metadata: ['email_hash' => sha1($email)]);
            abort(429, "Too many failed sign-in attempts. Try again in {$minutes} minutes.");
        }

        $user = User::where('email', $email)->first();

        // Hashes created by the Node API use bcrypt's $2b$ prefix; HASH_VERIFY=false
        // lets Hash::check accept them alongside Laravel's $2y$ hashes.
        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            RateLimiter::hit($lockKey, (int) config('navesta.auth.lockout_minutes') * 60);
            $this->audit->record('auth.login.failed', $user, ['email_hash' => sha1($email)]);
            abort(401, 'Invalid email or password');
        }

        RateLimiter::clear($lockKey);

        // Second step by SMS for customers who switched it on
        if ($user->two_factor_enabled && $user->hasVerifiedPhone()) {
            $challengeId = Str::random(40);
            Cache::put("login-challenge:{$challengeId}", ['user' => $user->id, 'attempts' => 0], now()->addMinutes((int) config('navesta.otp.ttl_minutes')));
            $expiresIn = $this->otp->send($user, 'login');
            $this->audit->record('auth.login.otp_sent', $user, actor: $user);

            return $this->ok([
                'requiresOtp' => true,
                'challengeId' => $challengeId,
                'phoneHint' => OtpService::maskPhone((string) $user->phone),
                'expiresInSeconds' => $expiresIn,
            ], 'Enter the code sent to your phone');
        }

        $this->footprint->capture($request, 'login', $user);
        $this->audit->record('auth.login.succeeded', $user, actor: $user);

        return $this->ok([
            'token' => $this->startSession($request, $user),
            'user' => $this->profile($user),
        ], 'Logged in successfully');
    }

    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challengeId' => ['required', 'string', 'size:40'],
            'code' => ['required', 'string', 'digits:'.config('navesta.otp.length')],
        ]);

        $key = "login-challenge:{$data['challengeId']}";
        $challenge = Cache::get($key);
        $user = is_array($challenge) ? User::find($challenge['user']) : null;

        if (! $user) {
            abort(401, 'This sign-in has expired. Please sign in again.');
        }

        if (! $this->otp->verify($user, 'login', $data['code'])) {
            $this->audit->record('auth.login.otp_failed', $user, actor: $user);
            abort(401, 'Incorrect or expired code');
        }

        Cache::forget($key);
        $this->footprint->capture($request, 'login_2fa_otp', $user);
        $this->audit->record('auth.login.succeeded', $user, ['second_factor' => 'sms'], actor: $user);

        return $this->ok([
            'token' => $this->startSession($request, $user),
            'user' => $this->profile($user),
        ], 'Logged in successfully');
    }

    public function sendPhoneLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        // Accept "98765 43210", "919876543210", "+91 98765-43210"...
        $phone = User::normalizePhone($data['phone']);
        if (! preg_match('/^\+91[6-9]\d{9}$/', $phone)) {
            abort(400, 'Enter a valid 10-digit Indian mobile number');
        }

        $challengeId = Str::random(40);
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('navesta.otp.ttl_minutes', 5);
        // Only a keyed hash of the code is kept, with a guess counter
        Cache::put("phone-login:{$challengeId}", [
            'phone' => $phone,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'attempts' => 0,
        ], now()->addMinutes($ttl));

        app(MessageGateway::class)->send(
            'sms',
            $phone,
            "Your Navesta sign-in verification code is {$code}. It expires in {$ttl} minutes.",
        );

        $this->audit->record('auth.phone.otp_sent', metadata: ['phone_masked' => OtpService::maskPhone($phone)]);

        $res = [
            'challengeId' => $challengeId,
            'phone' => OtpService::maskPhone($phone),
            'expiresInSeconds' => $ttl * 60,
        ];
        if (app()->environment('local', 'testing')) {
            $res['devCode'] = $code;
        }

        return $this->ok($res, 'Verification code sent to your mobile phone');
    }

    /**
     * Sign in, or sign up, with a mobile number and the code sent to it.
     * An unknown number only becomes an account when the request carries
     * the customer's explicit consent (sign-up form); a login attempt for
     * an unknown number is refused so nobody is enrolled without consent.
     */
    public function verifyPhoneLoginOtp(Request $request, ConsentService $consents): JsonResponse
    {
        $data = $request->validate([
            'challengeId' => ['required', 'string', 'size:40'],
            'code' => ['required', 'string', 'digits:6'],
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
            'acceptTerms' => ['sometimes', 'boolean'],
            'acceptMarketing' => ['sometimes', 'boolean'],
        ]);

        $key = "phone-login:{$data['challengeId']}";
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            abort(401, 'This code has expired. Please request a new one.');
        }

        // Local/testing only: 123456 works for a code that was really requested
        $isTestCode = app()->environment('local', 'testing') && $data['code'] === '123456';
        $matches = hash_equals($cached['code_hash'], hash_hmac('sha256', $data['code'], (string) config('app.key')));

        if (! $matches && ! $isTestCode) {
            $cached['attempts']++;
            if ($cached['attempts'] >= (int) config('navesta.otp.max_attempts', 5)) {
                Cache::forget($key);
                abort(401, 'Too many incorrect codes. Please request a new one.');
            }
            Cache::put($key, $cached, now()->addMinutes((int) config('navesta.otp.ttl_minutes', 5)));
            abort(401, 'Incorrect code. Please check the SMS and try again.');
        }

        Cache::forget($key);
        $phone = $cached['phone'];
        $user = User::findByPhone($phone);

        if (! $user) {
            if (empty($data['acceptTerms'])) {
                abort(404, 'No Navesta account uses this number yet. Create a free account to continue.');
            }
            if (empty($data['name'])) {
                abort(400, 'Please enter your name');
            }

            $user = DB::transaction(function () use ($data, $phone, $consents) {
                $user = new User([
                    'name' => $data['name'],
                    // Phone-first accounts get a placeholder address until the customer adds one
                    'email' => 'user_'.Str::lower(Str::random(12)).User::PHONE_ONLY_EMAIL_DOMAIN,
                    'phone' => $phone,
                    // No password: these accounts sign in with OTP only
                    'password_hash' => Hash::make(Str::random(64)),
                ]);
                $user->forceFill(['phone_verified_at' => now(), 'is_verified' => true])->save();

                $consents->record($user, 'terms', true);
                $consents->record($user, 'privacy', true);
                $consents->record($user, 'marketing', (bool) ($data['acceptMarketing'] ?? false));
                $consents->record($user, 'reminders', false);

                return $user;
            });

            $this->footprint->capture($request, 'registration', $user);
            $this->audit->record('auth.phone.registered', $user, actor: $user);
        } elseif (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now(), 'is_verified' => true])->save();
        }

        $this->footprint->capture($request, 'phone_otp_login', $user);
        $this->audit->record('auth.phone.login_succeeded', $user, actor: $user);

        return $this->ok([
            'token' => $this->startSession($request, $user),
            'user' => $this->profile($user),
        ], 'Signed in successfully via phone OTP');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasSession()) {
            // Website: end the cookie session
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            // Other clients: revoke the bearer token that made this request
            $user->currentAccessToken()->delete();
        }

        $this->audit->record('auth.logout', $user, actor: $user);

        return $this->ok(message: 'Logged out successfully');
    }

    /**
     * Session cookie for the website, bearer token for everything else.
     * Returns the token, or null when a session was started.
     */
    private function startSession(Request $request, User $user): ?string
    {
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return null;
        }

        $minutes = config('sanctum.expiration');

        return $user->createToken('api', ['*'], $minutes ? now()->addMinutes((int) $minutes) : null)->plainTextToken;
    }

    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'propertyStatus' => $user->property_status,
            'employmentSector' => $user->employment_sector,
        ];
    }
}
