<?php

namespace App\Migration;

use Carbon\Carbon;

/**
 * Reading of the migration state, built from facts about the environment.
 *
 * The house rule applies here too: every number on screen has to come from
 * somewhere. So nothing in this class is "marketing copy":
 *
 *  - PHP and framework versions come from the process that is answering;
 *  - package count and abandoned dependencies come from composer.lock;
 *  - PHP 8.4 blockers and suite results come from the snapshot written by
 *    `php artisan lab:snapshot` (which runs `composer why-not` and both real
 *    suites, and stores when it measured).
 *
 * Without a snapshot the page invents nothing: it shows "not measured" and
 * the command that fills it in. A stale snapshot is shown as stale.
 */
class Overview
{
    /** Abandoned dependencies and the planned replacement. */
    const ABANDONED = [
        'fzaninotto/faker' => 'fakerphp/faker',
        'laravelcollective/html' => 'remove (unused)',
    ];

    /**
     * Exit action per blocking package. Not generic advice: it is the math
     * that takes the 9 blockers to zero in this plan. Five of them don't
     * even need their own decision - they go away with PHPUnit 9.
     */
    const ACTIONS = [
        'laravel/laravel' => ['group' => 'framework', 'label' => 'adjust require.php'],
        'laravel/framework' => ['group' => 'framework', 'label' => 'bump to 6'],
        'fzaninotto/faker' => ['group' => 'replace', 'label' => 'replace with fakerphp/faker'],
        'beyondcode/laravel-dump-server' => ['group' => 'remove', 'label' => 'remove (dev only)'],
        'phpunit/phpunit' => ['group' => 'phpunit', 'label' => 'bump to 9'],
        'phpunit/php-code-coverage' => ['group' => 'phpunit', 'label' => 'ships with PHPUnit 9'],
        'phar-io/manifest' => ['group' => 'phpunit', 'label' => 'ships with PHPUnit 9'],
        'phar-io/version' => ['group' => 'phpunit', 'label' => 'ships with PHPUnit 9'],
        'sebastian/global-state' => ['group' => 'phpunit', 'label' => 'ships with PHPUnit 9'],
    ];

    const GROUP_LABELS = [
        'framework' => 'framework',
        'phpunit' => 'PHPUnit',
        'replace' => 'replace',
        'remove' => 'remove',
        'other' => 'assess',
    ];

    /**
     * The ladder. PHP is capped by the framework: each row is a deploy with
     * a rollback, not a checklist item to "sort out later".
     */
    const LADDER = [
        [
            'laravel' => '5.8',
            'php' => '7.2',
            'change' => 'Starting point: make:auth, Laravelcollective, abandoned Faker.',
            'gate' => 'Behavior frozen + green CI',
        ],
        [
            'laravel' => '6 → 8',
            'php' => '7.4',
            'change' => 'Runtime first, framework second. Laravel 8 is the bridge: it runs on 7.4 and on 8.0/8.1.',
            'gate' => 'vendor re-resolved, suite green',
        ],
        [
            'laravel' => '8',
            'php' => '8.0',
            'change' => 'First PHP 8 step: named arguments, match, nullsafe.',
            'gate' => 'Deprecations triaged',
        ],
        [
            'laravel' => '9',
            'php' => '8.1',
            'change' => 'Enums and readonly. ArrayAccess needs ReturnTypeWillChange.',
            'gate' => 'Where legacy code usually dies',
        ],
        [
            'laravel' => '10',
            'php' => '8.2',
            'change' => 'Deprecations become CI errors: dynamic properties, interpolation.',
            'gate' => 'CI without warnings',
        ],
        [
            'laravel' => '11 → 13',
            'php' => '8.4',
            'change' => 'Target: Laravel 13. 11 has had no security fixes since Mar 2026; 12 gets them until Feb 2027.',
            'gate' => 'End of the ladder',
        ],
    ];

    const RISKS = [
        [
            'title' => 'Runtime unpatched since Nov 2020',
            'detail' => 'PHP 7.2 is out of support and gets no security fixes. That risk sets the priority, not a new feature.',
            'where' => 'container · php 7.2',
        ],
        [
            'title' => 'Two abandoned dependencies',
            'detail' => 'fzaninotto/faker and laravelcollective/html (unused - just remove it). Clearing them before the first step avoids mixing two causes in one incident.',
            'where' => 'composer.lock',
        ],
        [
            'title' => 'Sync queue and plain-text token',
            'detail' => 'Deliberate demo decisions, listed on purpose: production needs a real worker and hashed tokens (Sanctum).',
            'where' => 'config/queue.php · ApiToken',
        ],
    ];

    /** Demo runbook: the order in which the story is told. */
    const RUNBOOK = [
        [
            'title' => 'State before touching anything',
            'detail' => 'This page: current step, measured blockers, green suites and what is not ready yet.',
            'route' => 'migration.index',
            'action' => 'Overview',
        ],
        [
            'title' => 'The product is not a CRUD',
            'detail' => 'Filtering and search in the database, summary in one query, trash, per-task audit trail.',
            'route' => 'tasks.index',
            'action' => 'Tasks',
        ],
        [
            'title' => 'Where the work is',
            'detail' => 'A project opens on its timeline; owner, member and viewer have different powers.',
            'route' => 'projects.index',
            'action' => 'Projects',
        ],
        [
            'title' => 'The process, not the card',
            'detail' => 'CFD, lead time p50/p85 and age of open work - all derived from the audit trail.',
            'route' => 'flow.index',
            'action' => 'Flow',
        ],
        [
            'title' => 'The edge is where attacks happen',
            'detail' => 'CSRF 419, IDOR 403, lock 409, throttle 429: the HTTP suite talks to the real server.',
            'command' => 'php artisan lab:verify',
        ],
        [
            'title' => 'The number that allows the step',
            'detail' => 'Characterization suite and blocker inventory. Without both, migrating is a gamble.',
            'command' => 'vendor/bin/phpunit · composer why-not php 8.4',
        ],
    ];

    /** @var string|null */
    private $snapshotPath;

    public function __construct($snapshotPath = null)
    {
        $this->snapshotPath = $snapshotPath ?: storage_path('app/migration/snapshot.json');
    }

    public function build()
    {
        $snapshot = $this->snapshot();
        $lock = $this->lock();

        $blockers = $this->blockers($snapshot);
        $suites = isset($snapshot['suites']) && is_array($snapshot['suites']) ? $snapshot['suites'] : [];

        $tests = 0;
        $assertions = 0;
        $green = !empty($suites);

        foreach ($suites as $suite) {
            $tests += (int) ($suite['tests'] ?? 0);
            $assertions += (int) ($suite['assertions'] ?? 0);
            $green = $green && !empty($suite['ok']);
        }

        $current = $this->currentStep($lock);

        return [
            'environment' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'packages' => $lock['count'],
                'root_php' => $lock['root_php'],
                'database' => config('database.default'),
            ],
            'ladder' => $this->ladder($current),
            'current' => $current,
            'next' => isset(self::LADDER[$current + 1]) ? self::LADDER[$current + 1] : null,
            'blockers' => $blockers,
            'blocker_groups' => $this->groupBlockers($blockers),
            'suites' => $suites,
            'totals' => [
                'tests' => $tests,
                'assertions' => $assertions,
                'green' => $green,
                'suites' => count($suites),
            ],
            'abandoned' => $this->abandoned($lock),
            'measured' => $this->measured($snapshot),
            'steps' => $this->steps($lock, $green, $blockers, $current, $suites),
            'risks' => self::RISKS,
            'runbook' => self::RUNBOOK,
        ];
    }

    // --------------------------------------------------------------- sources

    private function snapshot()
    {
        if (!is_file($this->snapshotPath)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->snapshotPath), true);

        return is_array($data) ? $data : null;
    }

    private function lock()
    {
        $path = base_path('composer.lock');
        $packages = [];

        if (is_file($path)) {
            $data = json_decode(file_get_contents($path), true);
            $all = array_merge(
                isset($data['packages']) ? $data['packages'] : [],
                isset($data['packages-dev']) ? $data['packages-dev'] : []
            );

            foreach ($all as $package) {
                if (isset($package['name'])) {
                    $packages[$package['name']] = $package;
                }
            }
        }

        $rootPhp = null;
        $composer = base_path('composer.json');
        if (is_file($composer)) {
            $json = json_decode(file_get_contents($composer), true);
            $rootPhp = isset($json['require']['php']) ? $json['require']['php'] : null;
        }

        return [
            'packages' => $packages,
            'count' => count($packages),
            'root_php' => $rootPhp,
        ];
    }

    // --------------------------------------------------------------- derived

    private function blockers($snapshot)
    {
        if (!$snapshot || empty($snapshot['blockers']) || !is_array($snapshot['blockers'])) {
            return [];
        }

        $rows = [];
        foreach ($snapshot['blockers'] as $blocker) {
            if (empty($blocker['name'])) {
                continue;
            }

            $action = isset(self::ACTIONS[$blocker['name']])
                ? self::ACTIONS[$blocker['name']]
                : ['group' => 'other', 'label' => 'assess replacement'];

            $rows[] = [
                'name' => $blocker['name'],
                'version' => isset($blocker['version']) ? $blocker['version'] : '-',
                'constraint' => isset($blocker['constraint']) ? $blocker['constraint'] : '-',
                'group' => $action['group'],
                'action' => $action['label'],
            ];
        }

        return $rows;
    }

    /** How many blockers each action clears - the math that becomes the plan. */
    private function groupBlockers(array $blockers)
    {
        $groups = [];
        foreach ($blockers as $blocker) {
            $key = $blocker['group'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => isset(self::GROUP_LABELS[$key]) ? self::GROUP_LABELS[$key] : $key,
                    'count' => 0,
                ];
            }
            $groups[$key]['count']++;
        }

        return array_values($groups);
    }

    private function abandoned(array $lock)
    {
        $found = [];
        foreach (self::ABANDONED as $package => $replacement) {
            if (isset($lock['packages'][$package])) {
                $found[] = [
                    'name' => $package,
                    'version' => $lock['packages'][$package]['version'],
                    'replacement' => $replacement,
                ];
            }
        }

        return $found;
    }

    /** Which step the project is on, read from vendor - not from a field. */
    private function currentStep(array $lock)
    {
        if (!isset($lock['packages']['laravel/framework'])) {
            return 0;
        }

        $version = ltrim($lock['packages']['laravel/framework']['version'], 'v');
        $major = (int) explode('.', $version)[0];

        // 5.8 -> 0, 6 -> 1, 7 -> 2, 8 -> 3, 9 -> 4, 10 -> 5, 11+ -> 5.
        $map = [5 => 0, 6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 5];

        return isset($map[$major]) ? $map[$major] : 5;
    }

    private function ladder($current)
    {
        $ladder = [];
        foreach (self::LADDER as $index => $step) {
            $step['index'] = $index;
            $step['current'] = $index === $current;
            $step['done'] = $index < $current;
            $step['target'] = $index === count(self::LADDER) - 1;
            $ladder[] = $step;
        }

        return $ladder;
    }

    private function measured($snapshot)
    {
        if (!$snapshot || empty($snapshot['measured_at'])) {
            return ['at' => null, 'label' => 'not measured', 'fresh' => false, 'command' => 'php artisan lab:snapshot'];
        }

        $at = Carbon::parse($snapshot['measured_at']);
        $minutes = (int) $at->diffInMinutes(Carbon::now());

        if ($minutes < 1) {
            $label = 'just now';
        } elseif ($minutes < 60) {
            $label = $minutes . ' min ago';
        } elseif ($minutes < 48 * 60) {
            $label = (int) round($minutes / 60) . ' h ago';
        } else {
            $label = (int) round($minutes / 1440) . ' days ago';
        }

        return [
            'at' => $at,
            'label' => $label,
            'fresh' => $minutes < 7 * 24 * 60,
            'command' => 'php artisan lab:snapshot',
        ];
    }

    /**
     * Next-step checklist, computed from the repo - not a fixed list with
     * bought green dots.
     */
    private function steps(array $lock, $green, array $blockers, $current, array $suites)
    {
        $ci = is_file(base_path('.github/workflows/ci.yml'));

        // From the snapshot, not a number typed into the code: "110 tests"
        // written here went stale the first time a test was added.
        $frozen = implode(' + ', array_map(function ($suite) {
            return (int) ($suite['tests'] ?? 0) . ' ' . ($suite['key'] ?? 'suite');
        }, $suites));

        return [
            [
                'label' => 'Behavior frozen',
                'ok' => $green,
                'detail' => $frozen !== '' ? $frozen . ' tests' . ($green ? ', green' : ', failing') : 'run php artisan lab:snapshot',
            ],
            [
                'label' => 'Blocker inventory measured',
                'ok' => !empty($blockers),
                'detail' => count($blockers) . ' packages block the jump',
            ],
            [
                'label' => 'Faker replaced',
                'ok' => !isset($lock['packages']['fzaninotto/faker']),
                'detail' => 'fzaninotto/faker → fakerphp/faker',
            ],
            [
                'label' => 'Laravelcollective removed',
                'ok' => !isset($lock['packages']['laravelcollective/html']),
                'detail' => 'laravelcollective/html is unused: remove it',
            ],
            [
                'label' => 'CI on every push',
                'ok' => $ci,
                'detail' => 'php -l · composer validate · phpunit · lab:verify',
            ],
            [
                'label' => 'First step applied',
                'ok' => $current > 0,
                'detail' => 'Laravel 6 · PHP 7.4 · rollback = previous deploy',
            ],
        ];
    }
}
