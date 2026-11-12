<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Measures the migration state and writes the snapshot the overview reads.
 *
 * Why a command and not numbers in the view: `composer why-not php 8.4` is
 * what drives the plan, and it changes whenever a dependency moves. Running
 * both suites here also prevents the classic "README says 110 tests" - the
 * stored number is what the machine answered, with a timestamp.
 *
 * If composer fails, the previous snapshot is kept: wiping the inventory
 * because of a wrong PATH would be worse than showing yesterday's data -
 * which the page flags as stale.
 */
class MigrationSnapshot extends Command
{
    protected $signature = 'lab:snapshot
                            {--skip-tests : Only record the blocker inventory (fast)}';

    protected $description = 'Measures blockers (composer why-not) and suites, and writes storage/app/migration/snapshot.json';

    public function handle()
    {
        $path = storage_path('app/migration/snapshot.json');
        $previous = $this->read($path);

        $snapshot = [
            'measured_at' => now()->toIso8601String(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'blockers' => $this->whyNot(),
            'suites' => [],
        ];

        if (empty($snapshot['blockers']) && !empty($previous['blockers'])) {
            $snapshot['blockers'] = $previous['blockers'];
            $this->warn('inventory could not be measured now; keeping the previous one');
        }

        if ($this->option('skip-tests')) {
            $snapshot['suites'] = isset($previous['suites']) ? $previous['suites'] : [];
        } else {
            // This process loaded .env and exported it; the child would inherit
            // the real DB_CONNECTION. The kernel suite gets its environment
            // spelled out, so RefreshDatabase can only touch :memory:.
            $snapshot['suites'][] = $this->suite(
                'kernel',
                'Kernel (in-memory SQLite)',
                'vendor/bin/phpunit',
                [PHP_BINARY, base_path('vendor/bin/phpunit')],
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => ':memory:',
                    'CACHE_DRIVER' => 'array',
                    'SESSION_DRIVER' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                    'MAIL_DRIVER' => 'array',
                ]
            );

            $snapshot['suites'][] = $this->suite(
                'http',
                'Edge (real HTTP)',
                'php artisan lab:verify',
                [PHP_BINARY, base_path('artisan'), 'lab:verify']
            );
        }

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            $this->error('could not create ' . dirname($path));

            return 1;
        }

        file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->line('');
        $this->info('snapshot: ' . $path);
        $this->line('PHP 8.4 blockers: ' . count($snapshot['blockers']));

        foreach ($snapshot['suites'] as $suite) {
            $this->line(sprintf(
                '%-8s %s (%d tests, %d assertions)',
                $suite['key'],
                $suite['ok'] ? 'ok' : 'failed',
                $suite['tests'],
                $suite['assertions']
            ));
        }

        return in_array(false, array_column($snapshot['suites'], 'ok'), true) ? 1 : 0;
    }

    /**
     * Real inventory: `composer why-not php 8.4` lists who declares a PHP
     * ceiling below 8.4. Each line becomes name, version and the blocking
     * constraint.
     */
    private function whyNot()
    {
        $process = new Process(['composer', 'why-not', 'php', '8.4', '--no-ansi'], base_path());
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->warn('composer unavailable: ' . $e->getMessage());

            return [];
        }

        if (!$process->isSuccessful()) {
            $this->warn('composer why-not failed: ' . trim($process->getErrorOutput()));

            return [];
        }

        $blockers = [];
        foreach (preg_split('/\R/', $process->getOutput()) as $line) {
            // name  version  requires  php (^7.1.3)
            if (preg_match('/^(\S+)\s{2,}(\S+)\s{2,}(?:requires|conflicts)\s+php\s+\(([^)]+)\)/', $line, $m)) {
                $blockers[] = [
                    'name' => $m[1],
                    'version' => $m[2],
                    'constraint' => $m[3],
                ];
            }
        }

        return $blockers;
    }

    /**
     * Runs the suite and extracts what matters from PHPUnit's own output:
     * counts, time and verdict. No guessing.
     */
    private function suite($key, $label, $command, array $arguments, array $env = [])
    {
        $process = new Process($arguments, base_path(), $env ?: null);
        $process->setTimeout(null);

        $this->line('running ' . $command . ' ...');
        $start = microtime(true);
        $process->run();
        $elapsed = microtime(true) - $start;

        $output = $process->getOutput() . $process->getErrorOutput();
        $tests = 0;
        $assertions = 0;
        $ok = false;

        if (preg_match('/OK \((\d+) tests?, (\d+) assertions?\)/', $output, $m)) {
            $ok = true;
            $tests = (int) $m[1];
            $assertions = (int) $m[2];
        } elseif (preg_match('/Tests: (\d+)(?:, Assertions: (\d+))?/', $output, $m)) {
            $tests = (int) $m[1];
            $assertions = isset($m[2]) ? (int) $m[2] : 0;
        }

        return [
            'key' => $key,
            'label' => $label,
            'command' => $command,
            'ok' => $ok && $process->isSuccessful(),
            'tests' => $tests,
            'assertions' => $assertions,
            'seconds' => round($elapsed, 2),
            'exit' => $process->getExitCode(),
        ];
    }

    private function read($path)
    {
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
