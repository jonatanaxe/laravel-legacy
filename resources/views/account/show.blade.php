@extends('layouts.app')

@section('title', 'API & account')

@section('content')
    @php
        $roleLabel = ['owner' => 'owner', 'member' => 'member', 'viewer' => 'viewer'];
        $roleBadge = ['owner' => 'badge-info', 'member' => 'badge-ok', 'viewer' => 'badge-muted'];
        $token = $user->api_token;
        $masked = $token ? substr($token, 0, 6) . str_repeat('•', 18) . substr($token, -4) : null;
    @endphp

    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">account</p>
                <h1>API &amp; account</h1>
                <p class="sub">Your details, the projects you belong to and access to the JSON API.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-ok" role="status">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Profile --}}
            <section class="panel panel-pad" aria-labelledby="profile-title">
                <h2 class="panel-title" id="profile-title">Profile</h2>

                <div class="flex items-center gap-3 mt-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-soft text-brand-600 text-lg font-semibold">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink truncate">{{ $user->name }}</p>
                        <p class="text-sm text-muted truncate">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-4 text-sm">
                    <div class="flex justify-between py-2 border-b border-line">
                        <dt class="text-muted">Member since</dt>
                        <dd class="m-0 text-ink">{{ optional($user->created_at)->format('M j, Y') ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between py-2 border-b border-line">
                        <dt class="text-muted">Personal tasks</dt>
                        <dd class="m-0 text-ink">{{ $user->tasks()->whereNull('project_id')->count() }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-muted">Projects</dt>
                        <dd class="m-0 text-ink">{{ $projects->count() }}</dd>
                    </div>
                </dl>
            </section>

            {{-- API --}}
            <section class="panel panel-pad lg:col-span-2" aria-labelledby="api-title" id="api">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="panel-title" id="api-title">API access</h2>
                        <p class="text-sm text-muted mt-1">
                            Read only, with the same access control as the screens. Limited to 60 requests per minute.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('account.token') }}"
                          data-confirm="Generate a new token? Integrations using the current one will stop working.">
                        @csrf
                        <button class="btn btn-outline btn-sm" type="submit">Generate new token</button>
                    </form>
                </div>

                @if ($token)
                    <div class="mt-4" x-data="{ show: false, copied: false }">
                        <label class="text-xs font-semibold text-muted uppercase tracking-wider" for="api-token">Your token</label>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <code class="flex-1 min-w-0 block px-3 py-2 rounded-md bg-surface-2 border border-line text-sm break-all" id="api-token"
                                  x-text="show ? '{{ $token }}' : '{{ $masked }}'">{{ $masked }}</code>
                            <button type="button" class="btn btn-ghost btn-sm" @click="show = !show"
                                    x-text="show ? 'Hide' : 'Show'">Show</button>
                            <button type="button" class="btn btn-ghost btn-sm"
                                    @click="navigator.clipboard.writeText('{{ $token }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                        </div>
                        <p class="text-xs text-muted mt-2">
                            Treat it like a password. It is stored in plain text (known limitation, see
                            <code>docs/cto-brief.md</code>).
                        </p>
                    </div>
                @else
                    <p class="mt-4 text-body">You don't have a token yet. Generate one to use the API.</p>
                @endif

                <h3 class="text-sm font-semibold text-ink mt-6">Endpoints</h3>
                <div class="scroll mt-2">
                    <table>
                        <thead><tr><th>Route</th><th>Returns</th></tr></thead>
                        <tbody>
                            <tr><td class="whitespace-no-wrap"><code>GET /api/user</code></td><td>who owns the token</td></tr>
                            <tr><td class="whitespace-no-wrap"><code>GET /api/tasks</code></td><td>visible tasks, paginated. Filters: <code>status</code> (all, open, done, overdue), <code>q</code>, <code>sort</code> (priority, due, recent)</td></tr>
                            <tr><td class="whitespace-no-wrap"><code>GET /api/tasks/{id}</code></td><td>a single task. 403 if it is neither yours nor your project's</td></tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="text-sm font-semibold text-ink mt-6">Example</h3>
                <pre class="mt-2 p-3 rounded-md bg-surface-2 border border-line text-sm overflow-x-auto"><code>curl -H "Authorization: Bearer YOUR_TOKEN" \
     "{{ $apiBase }}/tasks?status=open&amp;sort=due"</code></pre>
            </section>
        </div>

        {{-- Projects --}}
        <section class="panel mt-4" aria-labelledby="projects-title">
            <div class="panel-pad pb-0">
                <h2 class="panel-title" id="projects-title">Your projects</h2>
                <p class="text-sm text-muted mt-1">The role applies to every task in the project.</p>
            </div>

            @if ($projects->count())
                <ul class="list-none m-0 p-0 mt-3">
                    @foreach ($projects as $project)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 border-t border-line">
                            <a class="font-semibold text-ink hover:text-brand" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            <span class="badge {{ $roleBadge[$project->my_role] ?? 'badge-muted' }}">{{ $roleLabel[$project->my_role] ?? '-' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-6 py-4 text-body">
                    You are not in any project.
                    <a href="{{ route('projects.create') }}">Create a project</a>.
                </p>
            @endif
        </section>
    </div>
@endsection
