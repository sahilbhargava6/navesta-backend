<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Every error leaves the API as { success: false, error: "..." }, which is
// what the frontend's axios interceptor reads.
$error = fn (string $message, int $status, array $headers = [], array $extra = []) => response()->json(
    ['success' => false, 'error' => $message] + $extra,
    $status,
    $headers,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);

// The admin panel (/admin) and API docs keep Laravel's normal HTML handling.
$isApi = fn (Request $request) => $request->is('api/*', 'health');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::get('/health', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->api(prepend: [ForceJsonResponse::class]);
        // Requests from the website (SANCTUM_STATEFUL_DOMAINS) use the session
        // cookie + CSRF protection; all other clients use bearer tokens
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->alias(['idempotent' => Idempotency::class]);
        // Behind a load balancer: trust its X-Forwarded-* headers for client IPs and HTTPS
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error, $isApi) {
        // Error tracking; inactive until SENTRY_LARAVEL_DSN is set
        Integration::handles($exceptions);

        // JSON for the API; the admin panel and API docs keep their HTML pages
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request) || $request->expectsJson());

        $exceptions->render(fn (ValidationException $e, Request $request) => $isApi($request) ? $error($e->validator->errors()->first(), 400) : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $isApi($request)
            ? $error('Unauthorized access. Valid bearer token required.', 401)
            : null);

        $exceptions->render(fn (HttpExceptionInterface $e, Request $request) => $isApi($request) ? $error(
            $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
            $e->getStatusCode(),
            $e->getHeaders(),
        ) : null);

        $exceptions->render(function (Throwable $e, Request $request) use ($error, $isApi) {
            if (! $isApi($request) || $e instanceof HttpResponseException) {
                return null;
            }

            $debug = config('app.debug');

            return $error(
                $debug ? $e->getMessage() : 'Internal Server Error',
                500,
                extra: $debug ? ['stack' => $e->getTraceAsString()] : [],
            );
        });
    })->create();
