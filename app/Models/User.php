<?php

namespace App\Models;

use App\Enums\EmploymentSector;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property UserRole $role
 * @property PropertyStatus|null $property_status
 * @property EmploymentSector|null $employment_sector
 * @property bool $is_verified
 * @property bool $reminders_enabled
 * @property bool $two_factor_enabled
 * @property string|null $phone decrypted transparently
 * @property string|null $phone_hash keyed hash of the normalised phone (indexed lookup)
 * @property Carbon|null $phone_verified_at
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasApiTokens, HasUuids, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected $authPasswordName = 'password_hash';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'property_status',
        'employment_sector',
        'is_verified',
        'reminders_enabled',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $attributes = [
        'is_verified' => false,
        'reminders_enabled' => false,
        'two_factor_enabled' => false,
        'role' => 'customer',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'reminders_enabled' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'phone_verified_at' => 'datetime',
            // Encrypted at rest with the app key
            'phone' => 'encrypted',
            'role' => UserRole::class,
            'property_status' => PropertyStatus::class,
            'employment_sector' => EmploymentSector::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /**
     * Phone number used by the messaging notification channel.
     */
    public function routeNotificationForMessaging(): ?string
    {
        return $this->phone;
    }

    /** @return HasMany<CreditCard, $this> */
    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** @return HasMany<BtApplication, $this> */
    public function btApplications(): HasMany
    {
        return $this->hasMany(BtApplication::class);
    }

    /** @return HasMany<CreditScore, $this> */
    public function creditScores(): HasMany
    {
        return $this->hasMany(CreditScore::class);
    }

    /** @return HasOne<CreditScore, $this> */
    public function latestCreditScore(): HasOne
    {
        // Not latestOfMany(): its MAX(id) tie-breaker fails on Postgres UUID keys
        return $this->hasOne(CreditScore::class)->latest('fetched_at');
    }

    /** @return HasMany<DebtSnapshot, $this> */
    public function debtSnapshots(): HasMany
    {
        return $this->hasMany(DebtSnapshot::class);
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** @return HasMany<JourneyEvent, $this> */
    public function journeyEvents(): HasMany
    {
        return $this->hasMany(JourneyEvent::class);
    }

    /** @return HasOne<JourneyEvent, $this> */
    public function latestJourneyEvent(): HasOne
    {
        // Integer ids here, so latestOfMany()'s MAX(id) is safe (unlike UUID tables)
        return $this->hasOne(JourneyEvent::class)->latestOfMany();
    }

    protected static function booted(): void
    {
        // Keyed hash of the (encrypted) phone so sign-in can find the account
        // with an indexed lookup instead of decrypting every row
        static::saving(function (User $user) {
            if ($user->isDirty('phone')) {
                $user->phone_hash = $user->phone ? self::hashPhone($user->phone) : null;
            }
        });
    }

    /** Domain of the stand-in email given to accounts created with a mobile number only. */
    public const PHONE_ONLY_EMAIL_DOMAIN = '@phone.navesta.in';

    /**
     * Signed up with mobile + SMS code: no real email and no password they know.
     */
    public function isPhoneOnly(): bool
    {
        return str_ends_with((string) $this->email, self::PHONE_ONLY_EMAIL_DOMAIN);
    }

    /**
     * +91XXXXXXXXXX for Indian mobile numbers written as 10 digits, 91… or +91…
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return match (true) {
            strlen($digits) === 10 => '+91'.$digits,
            strlen($digits) === 12 && str_starts_with($digits, '91') => '+'.$digits,
            default => (str_starts_with(trim($phone), '+') ? '+' : '').$digits,
        };
    }

    public static function hashPhone(string $phone): string
    {
        return hash_hmac('sha256', self::normalizePhone($phone), (string) config('app.key'));
    }

    public static function findByPhone(string $phone): ?self
    {
        return static::where('phone_hash', self::hashPhone($phone))->first();
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /** @return HasMany<Statement, $this> */
    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }
}
