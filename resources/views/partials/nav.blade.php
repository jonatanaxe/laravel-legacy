{{-- Shared navbar: guests see Sign in/Create account; logged-in users see the app links + Sign out. --}}
<nav class="nav">
    <div class="wrap nav-inner">
        <a class="brand" href="{{ url('/') }}">
            <span class="glyph" aria-hidden="true">A</span>
            <span>{{ config('app.name', 'Atlas ERP') }}</span>
            <span class="brand-tag" aria-hidden="true">5.8 → 8.4</span>
        </a>

        <div class="nav-links">
            @if (request()->is('/'))
                <a class="nav-text" href="#concepts">Concepts</a>
                <a class="nav-text" href="#ladder">Migration</a>
                <a class="nav-text" href="#evidence">Evidence</a>
            @endif

            <a class="nav-text {{ request()->routeIs('migration.*') ? 'is-active' : '' }}"
               href="{{ route('migration.index') }}">Overview</a>

            @auth
                <a class="nav-text {{ request()->routeIs('tasks.*') ? 'is-active' : '' }}"
                   href="{{ route('tasks.index') }}">Tasks</a>
                <a class="nav-text {{ request()->routeIs('flow.*') ? 'is-active' : '' }}"
                   href="{{ route('flow.index') }}">Flow</a>
                <a class="nav-text {{ request()->routeIs('projects.*') ? 'is-active' : '' }}"
                   href="{{ route('projects.index') }}">Projects</a>

                {{-- Unread reminder counter: one query per logged-in page.
                     If it ever becomes a bottleneck, it moves to a cached view composer. --}}
                @php $unreadReminders = auth()->user()->unreadNotifications()->count(); @endphp
                <a class="nav-text {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}"
                   href="{{ route('notifications.index') }}">
                    Reminders
                    @if ($unreadReminders > 0)
                        <span class="badge badge-danger">{{ $unreadReminders }}</span>
                    @endif
                </a>

                <a class="nav-text {{ request()->routeIs('imports.*') ? 'is-active' : '' }}"
                   href="{{ route('imports.index') }}">Import</a>
            @endauth

            @guest
                <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Sign in</a>
                @if (Route::has('register'))
                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Create account</a>
                @endif
            @else
                <span class="nav-user" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>

                {{-- Logout is POST + @csrf: never a GET that changes the session --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            @endguest
        </div>
    </div>
</nav>
