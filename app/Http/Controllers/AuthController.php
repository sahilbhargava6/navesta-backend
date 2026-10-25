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
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+91)?[6-9]\d{9}$/'],
        ]);

        $rawPhone = preg_replace('/\s+/', '', $data['phone']);
        $phone = str_starts_with($rawPhone, '+91') ? $rawPhone : '+91'.$rawPhone;

        $challengeId = Str::random(40);
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('navesta.otp.ttl_minutes', 5);
        Cache::put("phone-login:{$challengeId}", ['phone' => $phone, 'code' => $code], now()->addMinutes($ttl));

        app(MessageGateway::class)->send(
            'sms',
            $phone,
            "Your Navesta sign-in verification code is {$code}. It expires in {$ttl} minutes.",
        );

        $this->audit->record('auth.phone.otp_sent', metadata: ['phone_masked' => OtpService::maskPhone($phone)]);

        return $this->ok([
            'challengeId' => $challengeId,
            'phone' => OtpService::maskPhone($phone),
            'expiresInSeconds' => $ttl * 60,
        ], 'Verification code sent to your mobile phone');
    }

    public function verifyPhoneLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challengeId' => ['required', 'string', 'size:40'],
            'code' => ['required', 'string', 'digits:6'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $key = "phone-login:{$data['challengeId']}";
        $cached = Cache::get($key);

        if (! $cached || $cached['code'] !== $data['code']) {
            abort(401, 'Invalid or expired verification code');
        }

        $phone = $cached['phone'];
        Cache::forget($key);

        // Find existing user with matching decrypted phone
        $user = User::all()->first(fn (User $u) => $u->phone === $phone);

        if (! $user) {
            $user = User::create([
                'name' => $data['name'] ?? 'Fintech Borrower',
                'email' => 'user_' . substr(md5($phone), 0, 8) . '@borrower.navesta.in',
                'phone' => $phone,
                'phone_verified_at' => now(),
                'is_verified' => true,
            ]);
            $this->audit->record('auth.phone.auto_registered', $user, actor: $user);
        } else {
            if (! $user->phone_verified_at) {
                $user->update(['phone_verified_at' => now(), 'is_verified' => true]);
            }
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
