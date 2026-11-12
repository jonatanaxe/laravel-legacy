<?php

namespace App\Migration;

/**
 * The test and blocker counts the home page quotes, read from the snapshot
 * `php artisan lab:snapshot` writes (the same one /migration reads).
 *
 * The page used to hard-code them and drifted (it said 182 tests when there
 * were 183). Without a snapshot this returns null and the page says
 * "not measured" instead of inventing a number.
 */
class Evidence
{
    private $snapshotPath;

    public function __construct($snapshotPath = null)
    {
        $this->snapshotPath = $snapshotPath ?: storage_path('app/migration/snapshot.json');
    }

    /**
     * @return array|null ['suites' => [key => suite], 'tests' => int, 'blockers' => int]
     */
    public function measured()
    {
        if (!is_file($this->snapshotPath)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->snapshotPath), true);

        if (!is_array($data) || empty($data['suites']) || !is_array($data['suites'])) {
            return null;
        }

        $suites = [];
        $tests = 0;

        foreach ($data['suites'] as $suite) {
            if (!isset($suite['key'])) {
                continue;
            }

            $suites[$suite['key']] = [
                'ok' => !empty($suite['ok']),
                'tests' => (int) ($suite['tests'] ?? 0),
                'assertions' => (int) ($suite['assertions'] ?? 0),
                'command' => $suite['command'] ?? '',
            ];
            $tests += $suites[$suite['key']]['tests'];
        }

        return [
            'suites' => $suites,
            'tests' => $tests,
            'blockers' => isset($data['blockers']) && is_array($data['blockers']) ? count($data['blockers']) : null,
        ];
    }
}
