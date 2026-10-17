<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\CreditScore;
use App\Models\User;
use App\Services\AccountDataService;
use App\Services\AuditLogger;
use App\Services\ConsentService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private ConsentService $consents,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('latestCreditScore');
        $score = $user->latestCreditScore;

        return $this->ok([
            ...$this->profile($user),
            'isVerified' => $user->is_verified,
            'phoneVerified' => $user->hasVerifiedPhone(),
            'twoFactorEnabled' => $user->two_factor_enabled,
            'remindersEnabled' => $user->reminders_enabled,
            'createdAt' => $user->created_at,
            'creditScores' => $score ? [$this->formatScore($score)] : [],
            'creditScore' => $score?->score,
            'consents' => $this->consents->current($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Empty values are ignored rather than clearing the field
        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'property_status' => $data['propertyStatus'] ?? null,
            'employment_sector' => $data['employmentSector'] ?? null,
        ]));

        // A new number must be verified again, and turns off SMS sign-in until it is
        if (! empty($data['phone'])) {
            $phone = preg_replace('/\s+/', '', $data['phone']);
            if ($phone !== $user->phone) {
                $user->phone = $phone;
                $user->phone_verified_at = null;
                $user->two_factor_enabled = false;
            }
        }

        if (array_key_exists('remindersEnabled', $data) && (bool) $data['remindersEnabled'] !== $user->reminders_enabled) {
            $user->reminders_enabled = (bool) $data['remindersEnabled'];
            $this->consents->record($user, 'reminders', $user->reminders_enabled);
        }

        if (array_key_exists('twoFactorEnabled', $data)) {
            if ($data['twoFactorEnabled'] && ! $user->hasVerifiedPhone()) {
                abort(400, 'Verify your phone number before turning on SMS sign-in codes');
            }
            $user->two_factor_enabled = (bool) $data['twoFactorEnabled'];
        }

        $user->save();

        return $this->ok([
            ...$this->profile($user),
            'phoneVerified' => $user->hasVerifiedPhone(),
            'twoFactorEnabled' => $user->two_factor_enabled,
            'remindersEnabled' => $user->reminders_enabled,
            'updatedAt' => $user->updated_at,
        ], 'Profile updated successfully');
    }

    public function sendPhoneCode(Request $request, OtpService $otp): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedPhone()) {
            abort(400, 'Your phone number is already verified');
        }

        $expiresIn = $otp->send($user, 'verify_phone');
        $this->audit->record('auth.phone.code_sent', $user);

        return $this->ok([
            'phoneHint' => OtpService::maskPhone((string) $user->phone),
            'expiresInSeconds' => $expiresIn,
        ], 'Verification code sent');
    }

    public function verifyPhone(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'digits:'.config('navesta.otp.length')]]);
        $user = $request->user();

        if (! $otp->verify($user, 'verify_phone', $data['code'])) {
            $this->audit->record('auth.phone.verify_failed', $user);
            abort(400, 'Incorrect or expired code');
        }

        $user->forceFill(['phone_verified_at' => now(), 'is_verified' => true])->save();

        return $this->ok(['phoneVerified' => true], 'Phone number verified');
    }

    /**
     * "Download my data" (DPDP Act right to access).
     */
    public function export(Request $request, AccountDataService $accountData): StreamedResponse
    {
        $user = $request->user();
        $this->audit->record('account.exported', $user);

        $json = json_encode($accountData->export($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(fn () => print $json, 'navesta-my-data-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Close the account and erase the customer's data (DPDP Act right to
     * erasure). Requires the password again.
     */
    public function destroy(Request $request, AccountDataService $accountData): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password_hash)) {
            abort(400, 'Incorrect password');
        }

        if ($user->isAdmin()) {
            abort(400, 'Admin accounts must be demoted by another admin before they can be deleted');
        }

        $accountData->erase($user);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->ok(message: 'Your account and data have been deleted');
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

    private function formatScore(CreditScore $score): array
    {
        return [
            'id' => $score->id,
            'userId' => $score->user_id,
            'score' => $score->score,
            'source' => $score->source,
            'fetchedAt' => $score->fetched_at,
        ];
    }
}
