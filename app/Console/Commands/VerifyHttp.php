<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Entry point of the edge suite (tests/Http) - the one that runs against the
 * live server.
 *
 * Why a command and not a shell script:
 *  - the project is PHP; orchestrating in bash means two languages for the
 *    same job, and nobody knows which php/phpunit was used;
 *  - here the binary is PHP_BINARY (the same one running artisan) and
 *    phpunit is the vendor one, so there is no "works on my machine";
 *  - the target comes from APP_URL (or --base), the URL the app already
 *    knows;
 *  - the exit code comes back intact, so it works as a CI gate.
 *
 * The suite is still PHPUnit (tests/Http): this command only orchestrates,
 * passing HTTP_BASE to the subprocess. CSRF, cookies, throttling and TLS are
 * proven by the real server - never by the in-process kernel.
 */
class VerifyHttp extends Command
{
    protected $signature = 'lab:verify
                            {--base= : Target URL (default: APP_URL)}
                            {--filter= : Run only the tests whose name matches the filter}
                            {--stop-on-failure : Stop at the first failing test}';

    protected $description = 'Runs the edge suite (real HTTP) against the live server';

    public function handle()
    {
        $base = rtrim($this->option('base') ?: config('app.url'), '/');

        // Fail early with a useful message: a server that is down would
        // produce dozens of connection errors and hide that the problem is
        // the environment.
        if (!$this->reachable($base)) {
            $this->error('no response at ' . $base . '/health');
            $this->line('start the server (madock start) and check APP_URL, or pass --base=...');

            return 1;
        }

        $arguments = [
            PHP_BINARY,
            base_path('vendor/bin/phpunit'),
            '-c',
            base_path('phpunit-http.xml'),
            '--testdox',
        ];

        if ($this->option('filter')) {
            $arguments[] = '--filter';
            $arguments[] = $this->option('filter');
        }

        if ($this->option('stop-on-failure')) {
            $arguments[] = '--stop-on-failure';
        }

        $this->line('target: ' . $base);

        $process = new Process($arguments, base_path());
        $process->setTimeout(null);

        // Only the URL changes: the rest of artisan's environment is inherited.
        $environment = $process->getEnv();
        $environment['HTTP_BASE'] = $base;
        $process->setEnv($environment);

        $process->run(function ($type, $output) {
            $this->output->write($output);
        });

        $exit = $process->getExitCode();

        if ($exit === 0) {
            $this->info('edge suite: ok');
        } else {
            $this->error('edge suite: failed (exit ' . $exit . ')');
        }

        return $exit;
    }

    /**
     * Does the server answer? Any status counts (503 included: that is a
     * finding about the app, not an environment that is down). This only
     * detects "nobody answers at this URL".
     */
    private function reachable($base)
    {
        $handle = curl_init(rtrim($base, '/') . '/health');

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            // Self-signed certificate of the local environment.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        return $error === 0 && $body !== false && $status > 0;
    }
}
