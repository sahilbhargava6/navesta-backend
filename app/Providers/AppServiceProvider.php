<?php

namespace App\Providers;

use App\Contracts\MessageGateway;
use App\Contracts\VirusScanner;
use App\Models\BtApplication;
use App\Models\CreditCard;
use App\Models\CreditScore;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\Post;
use App\Models\Statement;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\CacheInvalidationObserver;
use App\Services\Messaging\LogGateway;
use App\Services\Messaging\TwilioGateway;
use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\NullScanner;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MessageGateway::class, function () {
            $config = config('navesta.messaging');

            return match ($config['driver']) {
                'log' => new LogGateway,
                'twilio' => new TwilioGateway(
                    $config['twilio']['account_sid'],
                    $config['twilio']['auth_token'],
                    $config['twilio']['sms_from'],
                    $config['twilio']['whatsapp_from'],
                ),
                default => throw new InvalidArgumentException("Unknown MESSAGING_DRIVER [{$config['driver']}]"),
            };
        });

        $this->app->singleton(VirusScanner::class, function () {
            $config = config('navesta.statements');

            return match ($config['scanner']) {
                'none' => new NullScanner,
                'clamav' => new ClamAvScanner($config['clamav']['host'], $config['clamav']['port'], $config['clamav']['timeout']),
                default => throw new InvalidArgumentException("Unknown STATEMENT_SCANNER [{$config['scanner']}]"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $tooMany = fn (string $message) => fn (Request $request, array $headers) => response()->json([
            'statusCode' => 429,
            'error' => 'Too Many Requests',
            'message' => $message,
        ], 429, $headers);

        // API_RATE_LIMIT requests per minute (default 100): per customer when signed
        // in, per IP for guests, so customers sharing an office or mobile-carrier IP
        // don't throttle each other
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('navesta.api_rate_limit'))
            // The sanctum guard covers cookie sessions and bearer tokens; auth:sanctum reuses its result
            ->by(Auth::guard('sanctum')->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip())
            ->response($tooMany('Rate limit exceeded. Please try again in a minute.')));

        // Each reminder can trigger a paid WhatsApp/SMS message
        RateLimiter::for('reminders', fn (Request $request) => Limit::perHour(5)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Reminder limit reached. Please try again later.')));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(20)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Upload limit reached. Please try again later.')));

        // Route ids are UUIDs; anything else is a 404 instead of a database error
        Route::pattern('id', '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}');
        Route::pattern('loanId', '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}');

        // Audit trail of data changes, and cache invalidation
        foreach ([CreditCard::class, Loan::class, BtApplication::class, CreditScore::class, Lender::class, Post::class, User::class, Statement::class] as $model) {
            $model::observe(AuditObserver::class);
        }
        foreach ([CreditCard::class, Loan::class, CreditScore::class, Lender::class, User::class] as $model) {
            $model::observe(CacheInvalidationObserver::class);
        }

        // Passwords: 8+ characters with letters and numbers; production also
        // rejects passwords found in public breach lists (haveibeenpwned)
        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->numbers()
            ->when(app()->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        // Sign-in and sign-up abuse limits (per IP); per-email lockout is in AuthController
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip())
            ->response($tooMany('Too many sign-in attempts. Please wait a minute.')));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)
            ->by($request->ip())
            ->response($tooMany('Too many sign-ups from this network. Please try again later.')));

        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip())
            ->response($tooMany('Too many OTP requests. Please wait a minute.')));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Too many attempts. Please wait a minute.')));

        RateLimiter::for('account-export', fn (Request $request) => Limit::perHour(3)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Export limit reached. Please try again later.')));

        // Queue alerts: a backed-up queue or a job that failed for good
        Event::listen(function (QueueBusy $event) {
            report(new RuntimeException("Queue {$event->connection}:{$event->queue} is backed up ({$event->size} jobs)"));
        });
        Queue::failing(function (JobFailed $event) {
            Log::error('Queued job failed after all retries', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'error' => $event->exception->getMessage(),
            ]);
        });

        // API docs (/docs/api) are open locally and admin-only elsewhere
        Gate::define('viewApiDocs', fn (?User $user) => $user?->isAdmin() ?? false);

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }
}
