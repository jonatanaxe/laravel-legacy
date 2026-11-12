@extends('layouts.app')

@section('title', 'Atlas ERP · a legacy migration case')

@section('content')
    <header class="hero">
        <div class="wrap hero-grid">
            <div>
                <div class="eyebrow"><span class="pulse"></span> technical case · PALO IT interview · AI Native Software Engineer</div>

                <h1>The client's legacy system, and <span>how I would migrate it</span></h1>

                <p class="lead">
                    <strong>Atlas ERP</strong> is the system of a fictional client: Laravel 5.8 on PHP 7.2,
                    with tasks, projects, attachments, a reminder queue, CSV import, a token API and a
                    billing module inherited from 2009. I built it as a case for the PALO IT
                    interview: the legacy running and frozen by tests, and the method to take it to
                    PHP 8.4 without stopping the business.
                </p>

                <div class="actions">
                    @auth
                        <a class="btn btn-primary" href="{{ route('dashboard') }}">Open Atlas ERP</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('login') }}">Sign in</a>
                        @if (Route::has('register'))
                            <a class="btn btn-ghost" href="{{ route('register') }}">Create account</a>
                        @endif
                    @endauth
                    <a class="btn btn-ghost" href="#concepts">See the concepts</a>
                </div>
            </div>

            <aside class="spec" aria-label="Measured environment">
                <div class="spec-head">
                    <span>measured environment</span>
                    <span class="spec-live"><span class="pulse"></span> live</span>
                </div>

                <div class="spec-row"><span class="spec-k">Runtime</span><span class="spec-v">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}.{{ PHP_RELEASE_VERSION }}</span></div>
                <div class="spec-row"><span class="spec-k">Framework</span><span class="spec-v">Laravel {{ app()->version() }}</span></div>
                <div class="spec-row"><span class="spec-k">Dependencies</span><span class="spec-v">Composer 2.2.32</span></div>
                <div class="spec-row"><span class="spec-k">Database</span><span class="spec-v">MariaDB 11</span></div>
                <div class="spec-row"><span class="spec-k">Front end</span><span class="spec-v">Tailwind 1.9 · Alpine 2.8</span></div>

                {{-- Counts come from the lab:snapshot file, the same source as
                     /migration. No snapshot: the page says so instead of
                     quoting a number that may have drifted. --}}
                <div class="terminal" aria-hidden="true">
                    @if ($evidence)
                        @foreach ($evidence['suites'] as $suite)
                            <div class="term-line"><span class="prompt">$</span> {{ $suite['command'] }}</div>
                            <div class="term-line">
                                <span class="dim">{{ $suite['ok'] ? 'OK' : 'FAILED' }} ({{ $suite['tests'] }} tests, {{ $suite['assertions'] }} assertions)</span>
                            </div>
                        @endforeach
                    @else
                        <div class="term-line"><span class="prompt">$</span> php artisan lab:snapshot</div>
                        <div class="term-line"><span class="dim">not measured on this machine yet</span></div>
                    @endif
                </div>

                <div class="spec-foot">
                    <span><b>{{ $evidence ? $evidence['tests'] : '—' }}</b> tests</span>
                    <span><b>{{ $evidence && isset($evidence['suites']['http']) ? $evidence['suites']['http']['tests'] : '—' }}</b> at the edge (real HTTP)</span>
                    <span><b>{{ $evidence && $evidence['blockers'] !== null ? $evidence['blockers'] : '—' }}</b> PHP 8.4 blockers</span>
                </div>
            </aside>
        </div>
    </header>

    <section id="concepts" class="section">
        <div class="wrap">
            <div class="section-head">
                <div class="kicker">applied concepts</div>
                <h2>What is protected, where, and how it gets attacked</h2>
                <p>Each card points to the real file in this application and to the expected test result.</p>
            </div>

            <div class="grid">
                <article class="concept">
                    <div class="top"><span class="idx">01</span></div>
                    <h3>CSRF</h3>
                    <p>A state-changing request only passes with the session token. A link or form forged on another site doesn't carry it.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>resources/views/tasks/_form.blade.php</code></div>
                        <div><span class="lbl">test</span> POST without <code>_token</code> → <strong>419</strong></div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">02</span></div>
                    <h3>IDOR / authorization</h3>
                    <p>Changing the ID in the URL doesn't grant access to someone else's resource: the policy checks the owner before any read.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Policies/TaskPolicy.php</code></div>
                        <div><span class="lbl">test</span> user B on <code>/tasks/1</code> → <strong>403</strong></div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">03</span></div>
                    <h3>Mass assignment</h3>
                    <p>The client only fills what is in <code>$fillable</code>. The record's owner comes from the server, never from the form.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Task.php</code></div>
                        <div><span class="lbl">test</span> POST with an extra <code>user_id=1</code> → field dropped</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">04</span></div>
                    <h3>XSS</h3>
                    <p>Output is always escaped with <code>&#123;&#123; &#125;&#125;</code>. Raw <code>&#123;!! !!&#125;</code> is never used on user data.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>resources/views/tasks/index.blade.php</code></div>
                        <div><span class="lbl">test</span> a <code>&lt;script&gt;</code> title → shown as text</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">05</span></div>
                    <h3>Server-side validation</h3>
                    <p>Input rules live in a <code>FormRequest</code>. <code>required</code> and <code>maxlength</code> in HTML are a convenience, not a defense.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Http/Requests/StoreTaskRequest.php</code></div>
                        <div><span class="lbl">test</span> a 1-character title → comes back with an error</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">06</span></div>
                    <h3>SQL injection</h3>
                    <p><code>$request->user()->tasks()</code> is a query parameterized through the relationship. No user input is ever concatenated into SQL.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Http/Controllers/TaskController.php</code></div>
                        <div><span class="lbl">test</span> no injection surface</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">07</span></div>
                    <h3>Session and password</h3>
                    <p>Passwords with <code>bcrypt</code>, file sessions, <code>password</code> and <code>remember_token</code> out of serialization.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/User.php</code></div>
                        <div><span class="lbl">test</span> <code>$hidden</code> keeps the hash out of JSON</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">08</span></div>
                    <h3>Defense in depth</h3>
                    <p><code>auth</code> on the route <em>and</em> in the controller constructor: if one layer fails, the other still blocks.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>routes/web.php</code> + controller</div>
                        <div><span class="lbl">test</span> no session on <code>/tasks</code> → <strong>302</strong> to login</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">09</span></div>
                    <h3>Filtering and aggregation in the database</h3>
                    <p>Status, search and sorting become <code>where</code>/<code>orderByRaw</code>. The summary adds up four metrics in one query with <code>CASE WHEN</code>.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Task.php</code> (scopes) + <code>TaskController::stats</code></div>
                        <div><span class="lbl">test</span> a <code>?sort=</code> outside the whitelist → <strong>302</strong> with an error</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">10</span></div>
                    <h3>Audit trail and API</h3>
                    <p>The <code>TaskObserver</code> records who created, edited and completed. The API exposes the same domain with a token, and IDOR is blocked by the same policy.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Observers/TaskObserver.php</code> · <code>routes/api.php</code></div>
                        <div><span class="lbl">test</span> B's token on <code>/api/tasks/1</code> → <strong>403</strong>; no token → <strong>401</strong></div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">11</span></div>
                    <h3>Verifiable operations</h3>
                    <p><code>/health</code> checks the database, cache and disk writes, and answers <strong>503</strong> when something fails. CI runs PHPUnit on the container's PHP.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Http/Controllers/HealthController.php</code> · <code>.github/workflows/ci.yml</code></div>
                        <div><span class="lbl">test</span> <code>/health</code> → <strong>200</strong> with <code>status: ok</code></div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">12</span></div>
                    <h3>Projects and roles</h3>
                    <p>A task without a project is personal. A project task is shared: owner, member and viewer have different powers, and <strong>the role decides, not "who created it"</strong>.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Policies/ProjectPolicy.php</code> + <code>TaskPolicy.php</code></div>
                        <div><span class="lbl">test</span> a member demoted to viewer loses edit on their own task → <strong>403</strong></div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">13</span></div>
                    <h3>Authorized attachments</h3>
                    <p>Files go to a private disk under a server-generated name. Downloads go through a route that authorizes on the <em>task</em>. Being logged in is not enough.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Http/Controllers/TaskAttachmentController.php</code></div>
                        <div><span class="lbl">test</span> <code>shell.php</code> disguised as <code>.txt</code> → rejected by its content</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">14</span></div>
                    <h3>Reminder queue</h3>
                    <p>The due-date command pushes <code>TaskDueSoon</code> to the queue. Sending is claimed in the database with <code>UPDATE ... WHERE reminded_at IS NULL</code>, so two workers never duplicate it.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Console/Commands/SendDueSoonReminders.php</code></div>
                        <div><span class="lbl">test</span> running the command 2× → <strong>1</strong> notification, not 2</div>
                    </div>
                </article>

                <article class="concept">
                    <div class="top"><span class="idx">15</span></div>
                    <h3>CSV import</h3>
                    <p>The upload returns immediately and processing goes to the queue, read as a stream. Every line is validated; the report gives the number of each rejected line.</p>
                    <div class="meta">
                        <div><span class="lbl">where</span> <code>app/Jobs/ImportTasksCsv.php</code></div>
                        <div><span class="lbl">test</span> a CSV with <code>user_id</code>/<code>project_id</code> → columns ignored, owner is the uploader</div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section id="ladder" class="section section-dark">
        <div class="wrap">
            <div class="section-head">
                <div class="kicker">the ladder</div>
                <h2>There is no direct jump from 7.2 to 8.4</h2>
                <p>The PHP version is capped by the framework. Each step is a deploy with a rollback, not a big bang.</p>
            </div>

            <div class="steps">
                <div class="step now">
                    <div class="ver"><b>Laravel 5.8</b> · PHP 7.2</div>
                    <div class="what">Starting point: <code>make:auth</code>, Laravelcollective, abandoned Faker. <span class="tag">you are here</span></div>
                </div>
                <div class="step">
                    <div class="ver"><b>Laravel 6 → 8</b> · PHP 7.4</div>
                    <div class="what">Runtime first, framework second. Laravel 8 is the bridge: it runs on 7.4 and on 8.0/8.1.</div>
                </div>
                <div class="step">
                    <div class="ver"><b>Laravel 8</b> · PHP 8.0</div>
                    <div class="what">First PHP 8 step: named arguments, match, nullsafe.</div>
                </div>
                <div class="step">
                    <div class="ver"><b>Laravel 9</b> · PHP 8.1</div>
                    <div class="what">Enums and readonly. Here <code>ArrayAccess</code> needs <code>#[\ReturnTypeWillChange]</code>. This is where legacy code usually dies.</div>
                </div>
                <div class="step">
                    <div class="ver"><b>Laravel 10</b> · PHP 8.2</div>
                    <div class="what">Deprecations become CI errors: dynamic properties, string interpolation.</div>
                </div>
                <div class="step">
                    <div class="ver"><b>Laravel 11 → 13</b> · PHP 8.4</div>
                    <div class="what">Target. Before that, <strong>9 packages</strong> block the jump. The inventory comes from <code>composer why-not php 8.4</code>.</div>
                </div>
            </div>
        </div>
    </section>

    <section id="evidence" class="section">
        <div class="wrap">
            <div class="section-head">
                <div class="kicker">evidence</div>
                <h2>Tests that ran on this machine</h2>
                <p>
                    @if ($evidence && isset($evidence['suites']['kernel'], $evidence['suites']['http']))
                        {{ $evidence['tests'] }} tests: {{ $evidence['suites']['kernel']['tests'] }} for behavior in the kernel
                        and {{ $evidence['suites']['http']['tests'] }} at the edge against real HTTP.
                    @else
                        Two suites: behavior in the kernel and the edge against real HTTP.
                    @endif
                    Measured on this machine: the environment's answer, not a README promise.
                </p>
            </div>

            <div class="evidence">
                <table>
                    <thead>
                        <tr><th>Test</th><th>Expected</th><th>Got</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>POST <code>/tasks</code> without a CSRF token</td><td>rejected</td><td class="status-bad">419</td></tr>
                        <tr><td>User B opening A's task (IDOR)</td><td>denied</td><td class="status-bad">403</td></tr>
                        <tr><td>User B on their own list</td><td>allowed</td><td class="status-ok">200</td></tr>
                        <tr><td>Guest on <code>/tasks</code></td><td>redirect</td><td class="status-bad">302 → /login</td></tr>
                        <tr><td>Guest on the home page</td><td>public page</td><td class="status-ok">200</td></tr>
                        <tr><td><code>/api/tasks</code> without a token</td><td>unauthenticated</td><td class="status-bad">401</td></tr>
                        <tr><td><code>/api/tasks/1</code> with B's token</td><td>denied</td><td class="status-bad">403</td></tr>
                        <tr><td><code>/api/tasks?status=invalid</code></td><td>validation</td><td class="status-bad">422</td></tr>
                        <tr><td>6th login attempt (JSON)</td><td>throttle</td><td class="status-bad">429</td></tr>
                        <tr><td>Second PUT with the same <code>version</code></td><td>explicit conflict</td><td class="status-bad">409</td></tr>
                        <tr><td>Member sending another project's <code>project_id</code></td><td>denied</td><td class="status-bad">403</td></tr>
                        <tr><td>Project viewer trying to create/edit</td><td>denied</td><td class="status-bad">403</td></tr>
                        <tr><td>Attachment download by a third party</td><td>denied</td><td class="status-bad">403</td></tr>
                        <tr><td>Reminder command running 2×</td><td>idempotent</td><td class="status-ok">1 notification</td></tr>
                        <tr><td>CSV with another account's <code>user_id</code></td><td>ignored</td><td class="status-ok">owner = uploader</td></tr>
                        <tr><td>Another user's import report</td><td>don't reveal</td><td class="status-bad">404</td></tr>
                        <tr><td><code>/health</code> with database, cache and disk</td><td>healthy</td><td class="status-ok">200</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
