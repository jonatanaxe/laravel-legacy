{{--
    Application navigation (logged-in user). Everything the app does has an
    entry point here - before, Trash, Billing, API and Account had no link.

    Alpine 2: the `nav` state (mobile drawer) lives in the layout's x-data.
--}}
@php
    $unreadReminders = auth()->user()->unreadNotifications()->count();

    $groups = [
        'Work' => [
            ['route' => 'dashboard', 'match' => ['dashboard'], 'icon' => 'home', 'label' => 'Dashboard'],
            ['route' => 'tasks.index', 'match' => ['tasks.index', 'tasks.show', 'tasks.create', 'tasks.edit'], 'icon' => 'clipboard-list', 'label' => 'Tasks'],
            ['route' => 'projects.index', 'match' => ['projects.*'], 'icon' => 'folder', 'label' => 'Projects'],
            ['route' => 'flow.index', 'match' => ['flow.*'], 'icon' => 'chart-bar', 'label' => 'Flow'],
            ['route' => 'notifications.index', 'match' => ['notifications.*'], 'icon' => 'bell', 'label' => 'Reminders', 'count' => $unreadReminders],
        ],
        'Data' => [
            ['route' => 'imports.index', 'match' => ['imports.*'], 'icon' => 'upload', 'label' => 'Imports'],
            ['route' => 'tasks.trash', 'match' => ['tasks.trash'], 'icon' => 'trash', 'label' => 'Trash'],
            ['route' => 'billing.index', 'match' => ['billing.*'], 'icon' => 'cash', 'label' => 'Billing', 'tag' => 'SIG'],
        ],
        'System' => [
            ['route' => 'migration.index', 'match' => ['migration.*'], 'icon' => 'map', 'label' => 'Migration'],
            ['route' => 'account.show', 'match' => ['account.*'], 'icon' => 'key', 'label' => 'API & account'],
        ],
    ];
@endphp

<div class="flex h-full flex-col">
    <div class="flex items-center justify-between h-16 px-5 border-b border-line">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-ink hover:no-underline min-w-0">
            <span class="glyph" aria-hidden="true">A</span>
            <span class="min-w-0">
                <span class="block font-semibold leading-tight">Atlas ERP</span>
                <span class="block text-xs text-muted leading-tight">Atlas Services</span>
            </span>
        </a>
        <button type="button" class="lg:hidden btn btn-ghost btn-sm" @click="nav = false" aria-label="Close menu">
            @include('partials.icon', ['name' => 'x', 'class' => 'w-5 h-5'])
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Main">
        @foreach ($groups as $title => $items)
            <p class="px-3 {{ $loop->first ? "" : "mt-5" }} mb-1 text-xs font-semibold text-muted uppercase tracking-wider">{{ $title }}</p>
            <ul class="list-none m-0 p-0">
                @foreach ($items as $item)
                    @php $active = request()->routeIs(...$item['match']); @endphp
                    <li>
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:no-underline
                                  {{ $active ? 'bg-brand-soft text-brand-600 font-semibold' : 'text-body hover:bg-surface-2 hover:text-ink' }}"
                           @if ($active) aria-current="page" @endif>
                            @include('partials.icon', ['name' => $item['icon'], 'class' => 'w-5 h-5 flex-shrink-0'])
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @if (!empty($item['count']))
                                <span class="badge badge-danger" aria-label="{{ $item['count'] }} unread">{{ $item['count'] }}</span>
                            @endif
                            @if (!empty($item['tag']))
                                <span class="badge badge-muted">{{ $item['tag'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    {{-- Case signature + theme switch. The PALO IT theme only redefines the
         color tokens (ui.blade.php): no screen was touched for it to exist. --}}
    <div class="mx-3 mb-3 rounded-md border border-line bg-surface-2 px-3 py-2"
         x-data="{ brand: document.documentElement.dataset.brand }">
        <a href="{{ url('/') }}" class="block hover:no-underline">
            <span class="block text-xs font-semibold text-ink">Technical case · PALO IT</span>
            <span class="block text-xs text-muted">Legacy Laravel 5.8 / PHP 7.2</span>
        </a>

        <div class="mt-2 flex rounded-md border border-line-strong overflow-hidden text-xs" role="group" aria-label="Theme">
            <button type="button" class="flex-1 py-1 border-0 cursor-pointer"
                    :class="brand === 'atlas' ? 'bg-brand text-white font-semibold' : 'bg-surface text-body'"
                    @click="brand = 'atlas'; labSetBrand('atlas')">Atlas</button>
            <button type="button" class="flex-1 py-1 border-0 border-l border-line-strong cursor-pointer"
                    :class="brand === 'palo' ? 'bg-brand text-white font-semibold' : 'bg-surface text-body'"
                    @click="brand = 'palo'; labSetBrand('palo')">PALO IT</button>
        </div>
    </div>

    {{-- User: the menu opens upward. --}}
    <div class="relative border-t border-line p-3" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left bg-surface hover:bg-surface-2 border-0 cursor-pointer">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-600 text-sm font-semibold">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</span>
                <span class="block truncate text-xs text-muted">{{ auth()->user()->email }}</span>
            </span>
        </button>

        <div x-show="open" x-cloak @click.away="open = false" role="menu"
             class="absolute bottom-full left-0 right-0 mx-3 mb-1 rounded-lg border border-line bg-surface shadow-lg py-1">
            <a href="{{ route('account.show') }}" role="menuitem"
               class="flex items-center gap-2 px-4 py-2 text-sm text-ink hover:bg-surface-2 hover:no-underline">
                @include('partials.icon', ['name' => 'user-circle', 'class' => 'w-4 h-4'])
                My account
            </a>
            {{-- Logout is POST + @csrf: never a GET that changes the session --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-ink bg-surface hover:bg-surface-2 border-0 cursor-pointer">
                    @include('partials.icon', ['name' => 'logout', 'class' => 'w-4 h-4'])
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>
