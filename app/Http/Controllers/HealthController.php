<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Liveness/readiness check for load balancers and uptime monitors.
 * Returns 503 when the database or cache cannot be reached.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health-check', 1, 10);
                Cache::get('health-check');
            }),
        ];

        $healthy = ! in_array('down', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'Navesta Backend API',
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'checks' => $checks,
            'timestamp' => now()->toJSON(),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            Log::error('Health check failed', ['error' => $e->getMessage()]);

            return 'down';
        }
    }
}
