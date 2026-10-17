<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe retries for POST requests that create things. When a client sends
 * an `Idempotency-Key` header, the first response is stored for 24 hours
 * and replayed for any retry with the same key, so a double-click or a
 * network retry cannot submit two applications. Reusing a key for a
 * different request is refused.
 */
class Idempotency
{
    private const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($request->method() !== 'POST' || $key === null) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key)) {
            abort(400, 'Idempotency-Key must be 8-128 letters, digits, "-" or "_"');
        }

        $owner = $request->user()?->getAuthIdentifier() ?? $request->ip();
        $cacheKey = 'idempotency:'.sha1($owner.'|'.$key);
        $fingerprint = $this->fingerprint($request);

        $lock = Cache::lock($cacheKey.':lock', 30);
        if (! $lock->get()) {
            abort(409, 'A request with this Idempotency-Key is still being processed');
        }

        try {
            $stored = Cache::get($cacheKey);

            if (is_array($stored)) {
                if ($stored['fingerprint'] !== $fingerprint) {
                    abort(422, 'This Idempotency-Key was already used for a different request');
                }

                return response($stored['body'], $stored['status'], [
                    'Content-Type' => $stored['contentType'],
                    'Idempotent-Replayed' => 'true',
                ]);
            }

            $response = $next($request);

            // Server errors are not stored, so the client can retry them
            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'contentType' => $response->headers->get('Content-Type', 'application/json'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Identifies the request by route and parsed input (not raw bytes, so a
     * re-encoded multipart retry still matches).
     */
    private function fingerprint(Request $request): string
    {
        $files = array_map(
            fn ($file) => $file instanceof UploadedFile ? hash_file('sha256', $file->getRealPath()) : null,
            $request->allFiles(),
        );

        return hash('sha256', $request->path().'|'.json_encode($request->except(array_keys($files))).'|'.json_encode($files));
    }
}
