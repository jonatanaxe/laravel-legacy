@extends('layouts.app')

@section('title', 'Workflow')

@section('content')
    @php
        $cfd = $flow['cfd'];
        $throughput = $flow['throughput'];
        $aging = $flow['aging'];
        $times = $flow['times'];
        $summary = $flow['summary'];
        $stages = $flow['stages'];
        $cfdChart = $cfd['chart'];
        $tpChart = $throughput['chart'];
        $agingChart = $aging['chart'];
        $window = [7, 30, 60, 90];

        // Day metrics: null never becomes "0 days" - it becomes "-",
        // because an empty sample is not zero time.
        $d = function ($value) {
            return $value === null ? '-' : $value;
        };

        // CFD alt text, built in PHP: screen readers need the numbers, and
        // building it with @if inside <desc> is a Blade trap
        // (a directive glued to a word doesn't compile).
        $cfdDesc = [];
        foreach ($stages as $key => $label) {
            $cfdDesc[] = $label . ' ends with '
                . (isset($cfdChart['bands'][$key]) ? $cfdChart['bands'][$key]['last'] : 0)
                . ' tasks';
        }
    @endphp

    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">process · last {{ $days }} days</p>
                <h1>Workflow</h1>
                <p class="sub">
                    Not a board of tasks: it's the whole system over time.
                    Every number comes from the audit trail. Nothing here is typed by hand.
                </p>
            </div>

            <div class="actions">
                <nav class="chips" aria-label="Time window">
                    @foreach ($window as $size)
                        <a class="chip {{ $days === $size ? 'is-active' : '' }}"
                           href="{{ route('flow.index', ['days' => $size]) }}"
                           @if ($days === $size) aria-current="page" @endif>{{ $size }}d</a>
                    @endforeach
                </nav>
                <a class="btn btn-ghost" href="{{ route('tasks.index') }}">See tasks</a>
            </div>
        </header>

        <section class="stats" aria-label="Process summary">
            <div class="stat">
                <div class="stat-label">Open work</div>
                <div class="stat-value">{{ $summary['open'] }}</div>
                <div class="stat-note">{{ $summary['wip'] }} in progress now</div>
            </div>
            <div class="stat">
                <div class="stat-label">Done in 7 days</div>
                <div class="stat-value">{{ $summary['last7'] }}</div>
                <div class="stat-note">{{ $summary['per_week'] }} per week (4 weeks)</div>
            </div>
            <div class="stat">
                <div class="stat-label">Lead time p50</div>
                <div class="stat-value">
                    {{ $d($times['lead']['p50']) }}<span class="stat-unit">days</span>
                </div>
                <div class="stat-note">p85 {{ $d($times['lead']['p85']) }} · {{ $times['lead']['n'] }} done</div>
            </div>
            <div class="stat">
                <div class="stat-label">Forecast lead (Little)</div>
                <div class="stat-value">
                    {{ $d($summary['little_days']) }}<span class="stat-unit">days</span>
                </div>
                <div class="stat-note">WIP ÷ throughput, not a target</div>
            </div>
        </section>

        {{-- CFD: the centerpiece. Read bottom up (backlog at the base,
                  done at the top): work rises as it moves. --}}
        <section class="panel panel-pad chart-panel">
            <header class="chart-head">
                <div>
                    <h2 class="panel-title">Work by stage, day by day</h2>
                    <p class="hint">
                        Cumulative flow diagram. Each band's thickness is how much work
                        sits in that stage; the order is bottom up, from backlog
                        to done. A band that grows means more coming in than going out.
                    </p>
                </div>

                <ul class="legend">
                    @foreach ($stages as $key => $label)
                        <li class="legend-item">
                            <span class="swatch swatch-{{ $key }}" aria-hidden="true"></span>
                            {{ $label }}
                            <b>{{ isset($cfdChart['bands'][$key]) ? $cfdChart['bands'][$key]['last'] : 0 }}</b>
                        </li>
                    @endforeach
                </ul>
            </header>

            <figure class="chart">
                <svg viewBox="0 0 {{ $cfdChart['width'] }} {{ $cfdChart['height'] }}"
                     role="img" aria-labelledby="cfd-title cfd-desc">
                    <title id="cfd-title">Work by stage over the last {{ $days }} days</title>
                    <desc id="cfd-desc">Stacked area, one band per stage: {{ implode('; ', $cfdDesc) }}.</desc>

                    @foreach ($cfdChart['grid'] as $line)
                        <line class="chart-grid-line"
                              x1="{{ $cfdChart['plot']['x'] }}" y1="{{ $line['y'] }}"
                              x2="{{ $cfdChart['plot']['x'] + $cfdChart['plot']['w'] }}" y2="{{ $line['y'] }}" />
                        <text class="chart-axis" x="{{ $cfdChart['plot']['x'] - 8 }}"
                              y="{{ $line['y'] + 3.5 }}" text-anchor="end">{{ $line['value'] }}</text>
                    @endforeach

                    @foreach ($stages as $key => $label)
                        @if (isset($cfdChart['bands'][$key]))
                            <polygon class="band-{{ $key }}" points="{{ $cfdChart['bands'][$key]['points'] }}" />
                        @endif
                    @endforeach

                    {{-- Hairlines over the areas, top to bottom: without them
                              the bands turn into a smear of color. --}}
                    @foreach (array_reverse($stages, true) as $key => $label)
                        @if (isset($cfdChart['bands'][$key]))
                            <polyline class="band-edge" points="{{ $cfdChart['bands'][$key]['edge'] }}" />
                        @endif
                    @endforeach

                    @foreach ($cfdChart['ticks'] as $tick)
                        <text class="chart-axis" x="{{ $tick['x'] }}"
                              y="{{ $cfdChart['height'] - 9 }}" text-anchor="middle">{{ $tick['label'] }}</text>
                    @endforeach
                </svg>
            </figure>
        </section>

        <div class="grid-2">
            {{-- Throughput: length compares better than area, hence columns. --}}
            <section class="panel panel-pad">
                <header class="chart-head">
                    <div>
                        <h2 class="panel-title">Deliveries per week</h2>
                        <p class="hint mt-1">
                            {{ $throughput['total'] }} completions in
                            {{ count($throughput['labels']) }} weeks · best week:
                            {{ $throughput['best'] }}.
                        </p>
                    </div>
                </header>

                <figure class="chart">
                    <svg viewBox="0 0 {{ $tpChart['width'] }} {{ $tpChart['height'] }}"
                         role="img" aria-labelledby="tp-title">
                        <title id="tp-title">Completions per week</title>

                        @foreach ($tpChart['grid'] as $line)
                            <line class="chart-grid-line"
                                  x1="{{ $tpChart['plot']['x'] }}" y1="{{ $line['y'] }}"
                                  x2="{{ $tpChart['plot']['x'] + $tpChart['plot']['w'] }}" y2="{{ $line['y'] }}" />
                            <text class="chart-axis" x="{{ $tpChart['plot']['x'] - 8 }}"
                                  y="{{ $line['y'] + 3.5 }}" text-anchor="end">{{ $line['value'] }}</text>
                        @endforeach

                        @foreach ($tpChart['bars'] as $bar)
                            @php
                                // A week without deliveries becomes a 2px dash at
                                // the base: a missing bar is ambiguous (missing
                                // data or zero?); a dash means zero.
                                $height = $bar['h'] > 0 ? $bar['h'] : 2;
                                $top = $bar['base'] - $height;
                            @endphp
                            <rect class="bar {{ $bar['value'] === 0 ? 'is-zero' : '' }}"
                                  x="{{ $bar['x'] }}" y="{{ $top }}"
                                  width="{{ $bar['w'] }}" height="{{ $height }}" rx="2" />
                            @if ($bar['value'] > 0)
                                <text class="chart-value" x="{{ $bar['cx'] }}"
                                      y="{{ $top - 6 }}" text-anchor="middle">{{ $bar['value'] }}</text>
                            @endif
                        @endforeach

                        @foreach ($tpChart['ticks'] as $tick)
                            <text class="chart-axis" x="{{ $tick['x'] }}"
                                  y="{{ $tpChart['height'] - 9 }}" text-anchor="middle">{{ $tick['label'] }}</text>
                        @endforeach
                    </svg>
                </figure>
            </section>

            {{-- WIP aging: age is an honest proxy for risk. --}}
            <section class="panel panel-pad">
                <header class="chart-head">
                    <div>
                        <h2 class="panel-title">Age of open work</h2>
                        <p class="hint mt-1">
                            {{ $aging['open'] }} open · {{ $aging['stalled'] }} past 30 days.
                        </p>
                    </div>
                </header>

                <figure class="chart">
                    <svg viewBox="0 0 {{ $agingChart['width'] }} {{ $agingChart['height'] }}"
                         role="img" aria-labelledby="aging-title">
                        <title id="aging-title">Open tasks by age bracket</title>

                        @foreach ($agingChart['rows'] as $row)
                            <text class="hbar-label" x="0" y="{{ $row['label_y'] }}">{{ $row['label'] }}</text>
                            <rect class="hbar-track"
                                  x="{{ $agingChart['label_width'] }}" y="{{ $row['y'] }}"
                                  width="{{ $agingChart['track_w'] }}" height="{{ $agingChart['bar_h'] }}" rx="3" />
                            @if ($row['w'] > 0)
                                <rect class="hbar-fill {{ $row['warn'] ? 'is-warn' : '' }}"
                                      x="{{ $agingChart['label_width'] }}" y="{{ $row['y'] }}"
                                      width="{{ $row['w'] }}" height="{{ $agingChart['bar_h'] }}" rx="3" />
                            @endif
                            <text class="hbar-value" x="{{ $row['value_x'] }}"
                                  y="{{ $row['mid'] + 4 }}">{{ $row['value'] }}</text>
                        @endforeach
                    </svg>
                </figure>

                @if (count($aging['oldest']))
                    <ul class="stale-list">
                        @foreach ($aging['oldest'] as $oldest)
                            <li>
                                <a href="{{ route('tasks.show', $oldest['id']) }}">{{ $oldest['title'] }}</a>
                                <span class="age">{{ $oldest['age'] }} days old · {{ $oldest['status_label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="panel chart-panel">
            <div class="panel-pad">
                <header class="chart-head">
                    <div>
                        <h2 class="panel-title">How long work takes</h2>
                        <p class="hint mt-1">
                            Lead time is creation → completion; cycle time is work start →
                            completion. The difference between them is queue, not execution.
                        </p>
                    </div>
                </header>

                <table>
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>p50</th>
                            <th>p85</th>
                            <th>Mean</th>
                            <th>Worst case</th>
                            <th>Sample</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Lead time</strong></td>
                            <td>{{ $d($times['lead']['p50']) }} days</td>
                            <td>{{ $d($times['lead']['p85']) }} days</td>
                            <td>{{ $d($times['lead']['avg']) }} days</td>
                            <td>{{ $d($times['lead']['max']) }} days</td>
                            <td>{{ $times['lead']['n'] }}</td>
                        </tr>
                        <tr>
                            <td><strong>Cycle time</strong></td>
                            <td>{{ $d($times['cycle']['p50']) }} days</td>
                            <td>{{ $d($times['cycle']['p85']) }} days</td>
                            <td>{{ $d($times['cycle']['avg']) }} days</td>
                            <td>{{ $d($times['cycle']['max']) }} days</td>
                            <td>{{ $times['cycle']['n'] }}</td>
                        </tr>
                    </tbody>
                </table>

                @if ($times['efficiency'] !== null)
                    <p class="hint mt-3">
                        Flow efficiency: <strong>{{ $times['efficiency'] }}%</strong> of the lead time
                        was actual work; the rest was waiting. Below 25%, the bottleneck
                        is the queue, not the people doing the work.
                    </p>
                @endif
            </div>
        </section>

        <section class="panel panel-pad chart-panel">
            <header class="chart-head">
                <div>
                    <h2 class="panel-title">How these numbers are measured</h2>
                    <p class="hint mt-1">
                        No metrics table and no cron recounting: everything derives from
                        <code>task_events</code>, the trail that already existed for auditing.
                    </p>
                </div>
            </header>

            <ul class="method">
                <li><strong>Source:</strong> the observer records every stage change on any
                    write path: web, API, CSV import or artisan. There is no path
                    that writes a task without leaving an event.</li>
                <li><strong>CFD:</strong> for each day, counts which stage each task was in at the
                    end of that day. Nothing is interpolated, nothing is filled in by hand.</li>
                <li><strong>p85 instead of the mean:</strong> a task forgotten for 300 days
                    skews the mean and doesn't skew the p85, and the p85 is what gets promised to the client.</li>
                <li><strong>Little's Law:</strong> WIP ÷ throughput estimates the lead time. If the
                    estimate diverges a lot from the p50, there is open work that isn't
                    being counted as work.</li>
                <li><strong>Declared limit:</strong> trashed tasks leave the whole series;
                    tasks without events enter as backlog on their creation date. Legacy data
                    doesn't get an invented history.</li>
            </ul>
        </section>
    </div>
@endsection
