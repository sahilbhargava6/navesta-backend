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
