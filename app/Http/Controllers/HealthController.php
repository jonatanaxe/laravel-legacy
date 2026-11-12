<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * /health - what a monitor (or the load balancer) asks.
 *
 * Not "does the page open?" but "do the dependencies answer?". Returns 200
 * when everything passes and 503 when any check fails, which is the code an
 * orchestrator understands to take the instance out of rotation.
 *
 * Leaks no secrets: no APP_KEY, no DB password, no connection string. The
 * framework version is exposed on purpose (operations needs to know what is
 * running); acceptable in a closed environment. The app footer links to it
 * for logged-in users only; a public deployment would put it behind the
 * internal network instead.
 */
class HealthController extends Controller
{
    public function show()
    {
        $checks = [
            'database' => $this->check(function () {
                DB::select('select 1');
            }),
            'cache' => $this->check(function () {
                $key = 'health:' . getmypid();
                Cache::put($key, 'ok', 1);
                if (Cache::get($key) !== 'ok') {
                    throw new \RuntimeException('cache did not return the stored value');
                }
                Cache::forget($key);
            }),
            'storage' => $this->check(function () {
                $file = storage_path('framework/health.tmp');
                if (file_put_contents($file, 'ok') === false) {
                    throw new \RuntimeException('storage is not writable');
                }
                @unlink($file);
            }),
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'), true);

        $body = [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'time' => now()->toAtomString(),
        ];

        return response()->json($body, $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Runs the check and times it. An exception becomes "ok: false" with the
     * error class - no stack trace in the public response.
     */
    private function check(callable $probe)
    {
        $start = microtime(true);

        try {
            $probe();

            return [
                'ok' => true,
                'ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'ms' => round((microtime(true) - $start) * 1000, 1),
                'error' => class_basename($e),
            ];
        }
    }
}
