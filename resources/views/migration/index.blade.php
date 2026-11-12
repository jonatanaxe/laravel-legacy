@extends('layouts.app')

@section('title', 'Migration overview')

{{--
    Entry point of the case: the migration state before any change.

    The page is a dashboard, not a landing page. Every number has a source
    (environment, composer.lock or a measured snapshot), and what was not measured
    shows as not measured - never as zero.
--}}
@section('content')
    @php
        $env = $overview['environment'];
        $current = $overview['current'];
        $now = $overview['ladder'][$current];
        $next = $overview['next'];
        $blockers = $overview['blockers'];
        $totals = $overview['totals'];
        $measured = $overview['measured'];
        $abandoned = $overview['abandoned'];
        $stepCount = count($overview['ladder']) - 1;
        // Not measured is not zero: without a snapshot, counts show a dash.
        $isMeasured = $measured['at'] !== null;
        $hasSuites = (bool) $totals['suites'];
    @endphp

    <header class="hero hero-dash">
        <div class="wrap hero-grid">
            <div>
                <div class="eyebrow">
                    <span class="pulse"></span>
                    overview · laravel {{ $now['laravel'] }} · php {{ $now['php'] }}
                </div>

                <h1>Where the legacy stands, and what the <span>next step</span> is</h1>

                <p class="lead">
                    A real Laravel 5.8 / PHP 7.2 app used as a migration rehearsal:
                    @if ($isMeasured)
                        behavior frozen by {{ $totals['tests'] }} tests,
                        {{ count($blockers) }} measured dependency blockers and every step
                        with a deploy and a rollback.
                    @else
                        behavior frozen by tests, dependency blockers measured by
                        <code>composer why-not</code> and every step with a deploy and a rollback.
                    @endif
                    The numbers on this page come from the environment, not from the README.
                </p>

                <div class="actions">
                    <a class="btn btn-primary" href="#runbook">6-minute runbook</a>
                    <a class="btn btn-ghost" href="#blockers">{{ $isMeasured ? 'The ' . count($blockers) . ' blockers' : 'Blocker inventory' }}</a>
                    <a class="btn btn-ghost" href="{{ url('/') }}#concepts">Concepts and defenses</a>
                </div>
            </div>

            <aside class="spec" aria-label="Measured environment">
                <div class="spec-head">
                    <span>measured environment</span>
                    <span class="spec-live {{ $measured['fresh'] ? '' : 'spec-stale' }}">
                        <span class="pulse"></span> {{ $measured['label'] }}
                    </span>
                </div>

                <div class="spec-row"><span class="spec-k">Runtime</span><span class="spec-v">PHP {{ $env['php'] }}</span></div>
                <div class="spec-row"><span class="spec-k">Framework</span><span class="spec-v">Laravel {{ $env['laravel'] }}</span></div>
                <div class="spec-row"><span class="spec-k">Installed packages</span><span class="spec-v">{{ $env['packages'] }}</span></div>
                <div class="spec-row"><span class="spec-k">PHP 8.4 blockers</span><span class="spec-v">{{ $isMeasured ? count($blockers) : 'not measured' }}</span></div>
                <div class="spec-row"><span class="spec-k">Database</span><span class="spec-v">{{ $env['database'] }}</span></div>

                <div class="terminal" aria-hidden="true">
                    @forelse ($totals['suites'] ? $overview['suites'] : [] as $suite)
                        <div class="term-line"><span class="prompt">$</span> {{ $suite['command'] }}</div>
                        <div class="term-line">
                            <span class="dim">
                                {{ $suite['ok'] ? 'OK' : 'FAILED' }}
                                ({{ $suite['tests'] }} tests, {{ $suite['assertions'] }} assertions)
                            </span>
                        </div>
                    @empty
                        <div class="term-line"><span class="prompt">$</span> php artisan lab:snapshot</div>
                        <div class="term-line"><span class="dim">not measured yet</span></div>
                    @endforelse
                </div>

                <div class="spec-foot">
                    <span><b>{{ $hasSuites ? $totals['tests'] : '—' }}</b> tests</span>
                    <span><b>{{ count($abandoned) }}</b> abandoned</span>
                    <span><b>{{ $stepCount }}</b> steps</span>
                </div>
            </aside>
        </div>
    </header>

    <div class="wrap">
        <section aria-label="Migration summary">
            <div class="stats stats-dash">
                <div class="stat">
                    <div class="stat-label">Current step</div>
                    <div class="stat-value">{{ $current + 1 }}<span class="stat-unit">/ {{ $stepCount }}</span></div>
                    <div class="stat-note">Laravel {{ $now['laravel'] }} · PHP {{ $now['php'] }}</div>
                </div>

                <div class="stat {{ !$isMeasured ? '' : (count($blockers) ? 'is-danger' : 'is-ok') }}">
                    <div class="stat-label">PHP 8.4 blockers</div>
                    <div class="stat-value">{{ $isMeasured ? count($blockers) : '—' }}</div>
                    <div class="stat-note">
                        @if (!$isMeasured)
                            not measured yet
                        @elseif ($blockers)
                            {{ count($overview['blocker_groups']) }} exit tracks
                        @else
                            nothing blocks the jump
                        @endif
                    </div>
                </div>

                <div class="stat {{ $totals['green'] ? 'is-ok' : '' }}">
                    <div class="stat-label">Green tests</div>
                    <div class="stat-value">{{ $hasSuites ? $totals['tests'] : '—' }}</div>
                    <div class="stat-note">{{ $hasSuites ? $totals['assertions'] . ' assertions' : 'not measured yet' }}</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Abandoned dependencies</div>
                    <div class="stat-value">{{ count($abandoned) }}</div>
                    <div class="stat-note">cleared before step 1</div>
                </div>
            </div>
        </section>

        {{-- The ladder: the main reading. The PHP version is capped by the
                  framework, so the path has an order and each row is a deploy. --}}
        <section class="panel panel-pad mt-4" id="steps" aria-labelledby="steps-title">
            <div class="chart-head">
                <div>
                    <p class="kicker">the ladder</p>
                    <h2 id="steps-title">{{ $stepCount }} steps, one deploy at a time</h2>
                    <p class="hint">
                        There is no direct jump from 7.2 to 8.4. Each step has its own gate
                        and rollback = the previous deploy; the new app and the old one
                        share the same database until the next step.
                    </p>
                </div>
            </div>

            <ol class="rail">
                @foreach ($overview['ladder'] as $step)
                    <li class="rail-step {{ $step['current'] ? 'is-current' : '' }} {{ $step['target'] ? 'is-target' : '' }} {{ $step['done'] ? 'is-done' : '' }}">
                        <span class="rail-dot" aria-hidden="true"></span>
                        <span class="rail-ver">Laravel {{ $step['laravel'] }}</span>
                        <span class="rail-php">PHP {{ $step['php'] }}</span>
                        <span class="rail-what">{{ $step['change'] }}</span>
                        <span class="rail-gate">{{ $step['gate'] }}</span>
                        @if ($step['current'])
                            <span class="badge badge-info">you are here</span>
                        @elseif ($step['target'])
                            <span class="badge badge-ok">target</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="split">
            <div>
                {{-- Blockers: the inventory that drives the plan. --}}
                <section class="panel" id="blockers" aria-labelledby="blockers-title">
                    <div class="panel-head">
                        <p class="kicker">inventory</p>
                        <h2 id="blockers-title">What blocks the jump to PHP 8.4</h2>
                        <p class="hint">
                            Literal output of <code>composer why-not php 8.4</code>. Each package
                            declares a PHP ceiling below the target. Not an opinion: it is the
                            constraint in its own <code>composer.json</code>.
                        </p>

                        @if ($blockers)
                            <div class="blocker-summary" aria-label="Blockers by exit track">
                                @foreach ($overview['blocker_groups'] as $group)
                                    <span class="blocker-tag"><b>{{ $group['count'] }}</b> {{ $group['label'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($blockers)
                        <div class="table-scroll">
                            <table class="blockers">
                                <thead>
                                    <tr>
                                        <th scope="col">Package</th>
                                        <th scope="col">Version</th>
                                        <th scope="col">Requires PHP</th>
                                        <th scope="col">How it goes away</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($blockers as $blocker)
                                        <tr>
                                            <td><code>{{ $blocker['name'] }}</code></td>
                                            <td class="mono">{{ $blocker['version'] }}</td>
                                            <td><span class="badge badge-danger">{{ $blocker['constraint'] }}</span></td>
                                            <td>{{ $blocker['action'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($isMeasured)
                        <div class="empty">
                            <h3>Nothing blocks the jump</h3>
                            <p>Every installed package already accepts PHP 8.4.</p>
                        </div>
                    @else
                        <div class="empty">
                            <h3>Inventory not measured yet</h3>
                            <p>
                                The page doesn't invent blockers: run the command and it starts
                                showing the real output of <code>composer why-not php 8.4</code>.
                            </p>
                            <code>php artisan lab:snapshot</code>
                        </div>
                    @endif
                </section>

                {{-- The oracle: without green tests, the next step is a gamble. --}}
                <section class="panel panel-pad mt-4" id="evidence" aria-labelledby="evidence-title">
                    <div class="chart-head">
                        <div>
                            <p class="kicker">the oracle</p>
                            <h2 id="evidence-title">Tests that ran on this machine</h2>
                            <p class="hint">
                                Two suites with different purposes: the kernel proves the rules;
                                the edge speaks real HTTP, which is where CSRF, cookies, throttling and
                                TLS exist.
                            </p>
                        </div>
                    </div>

                    @if ($overview['suites'])
                        <div class="grid-2">
                            @foreach ($overview['suites'] as $suite)
                                <article class="oracle">
                                    <div class="oracle-head">
                                        <span class="badge {{ $suite['ok'] ? 'badge-ok' : 'badge-danger' }}">
                                            {{ $suite['ok'] ? 'green' : 'failed' }}
                                        </span>
                                        <span class="oracle-ms">{{ $suite['seconds'] }}s</span>
                                    </div>
                                    <p class="oracle-value">
                                        {{ $suite['tests'] }}<span class="stat-unit">tests</span>
                                    </p>
                                    <p class="oracle-note">
                                        {{ $suite['assertions'] }} assertions · {{ $suite['label'] }}
                                    </p>
                                    <code class="oracle-cmd">{{ $suite['command'] }}</code>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="empty">
                            <h3>No suite measured</h3>
                            <p>The command below runs both and records counts, time and verdict.</p>
                            <code>php artisan lab:snapshot</code>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="split-aside">
                {{-- Checklist computed from the repo: nothing here is ticked by hand. --}}
                <section class="panel panel-pad" aria-labelledby="next-title">
                    <p class="kicker">next step</p>
                    <h2 id="next-title" class="panel-title">
                        @if ($next)
                            Laravel {{ $next['laravel'] }} · PHP {{ $next['php'] }}
                        @else
                            End of the ladder
                        @endif
                    </h2>
                    <p class="hint">
                        Each item is checked against the code and <code>composer.lock</code>
                        Including the ones that are not ready yet.
                    </p>

                    <ul class="checklist">
                        @foreach ($overview['steps'] as $step)
                            <li class="{{ $step['ok'] ? 'is-ok' : '' }}">
                                <span class="check-mark" aria-hidden="true">{{ $step['ok'] ? '✓' : '·' }}</span>
                                <span class="check-body">
                                    <b>{{ $step['label'] }}</b>
                                    <small>{{ $step['detail'] }}</small>
                                </span>
                                <span class="sr-only">{{ $step['ok'] ? 'done' : 'pending' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="panel panel-pad" aria-labelledby="risks-title">
                    <p class="kicker">risks</p>
                    <h2 id="risks-title" class="panel-title">What is not ready, on purpose</h2>

                    <ul class="risk-list">
                        @foreach ($overview['risks'] as $risk)
                            <li>
                                <b>{{ $risk['title'] }}</b>
                                <span>{{ $risk['detail'] }}</span>
                                <code>{{ $risk['where'] }}</code>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="panel panel-pad" aria-labelledby="source-title">
                    <p class="kicker">provenance</p>
                    <h2 id="source-title" class="panel-title">Where these numbers come from</h2>
                    <p class="hint">
                        Environment and packages are read on every request. Blockers and suites come
                        from the snapshot written by the command below.
                    </p>

                    <div class="terminal terminal-flush" aria-hidden="true">
                        <div class="term-line"><span class="prompt">$</span> php artisan lab:snapshot</div>
                        <div class="term-line"><span class="dim">writes storage/app/migration/snapshot.json</span></div>
                    </div>

                    <p class="hint">
                        @if ($measured['at'])
                            Measured {{ $measured['label'] }}.
                            @if (!$measured['fresh'])
                                <strong>Stale snapshot:</strong> measure again before showing it.
                            @endif
                        @else
                            <strong>Not measured yet.</strong> Run the command before the demo.
                        @endif
                    </p>
                </section>
            </aside>
        </div>

        {{-- Runbook: the order in which the story is told, with a link to each
                  screen. It is what turns the app into a demo and not a tour. --}}
        <section class="mt-6" id="runbook" aria-labelledby="runbook-title">
            <div class="section-head">
                <p class="kicker">runbook</p>
                <h2 id="runbook-title" class="panel-title">Six minutes, in this order</h2>
                <p>
                    State first, then the product, then the edge. Every step ends
                    in a number or an HTTP response, never in "trust me".
                </p>
            </div>

            <ol class="runbook">
                @foreach ($overview['runbook'] as $index => $step)
                    <li>
                        <span class="runbook-n" aria-hidden="true">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="runbook-main">
                            <b>{{ $step['title'] }}</b>
                            <span>{{ $step['detail'] }}</span>
                        </div>
                        <div class="runbook-act">
                            @if (isset($step['route']) && Route::has($step['route']))
                                <a class="btn btn-outline btn-sm" href="{{ route($step['route']) }}">{{ $step['action'] }}</a>
                            @else
                                <code>{{ $step['command'] }}</code>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection
