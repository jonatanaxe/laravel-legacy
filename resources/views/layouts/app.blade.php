<!DOCTYPE html>
@php
    // Theme: only whitelisted values become an attribute. The cookie is not
    // encrypted (EncryptCookies::$except), hence the validation here.
    $brand = request()->cookie('brand') === 'palo' ? 'palo' : 'atlas';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-brand="{{ $brand }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Atlas ERP'))</title>

    {{-- Tokens + components: the same CSS on every page, no build. --}}
    @include('partials.ui')

    {{-- Compiled Tailwind 1 (npm run css:build). Loaded AFTER the legacy CSS
         so utilities win over the old classes on a tie. --}}
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}?v={{ @filemtime(public_path('css/tailwind.css')) }}">

    {{-- Own JS (no inline handlers): the delete confirm() --}}
    <script src="{{ asset('js/lab.js') }}" defer></script>

    {{-- Alpine 2.8 (local file). Requires 'unsafe-eval' in the CSP: see
         SecurityHeaders. --}}
    <script src="{{ asset('js/alpine.min.js') }}" defer></script>
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    {{-- Logged in: application layout (sidebar). Guests and the presentation
         page (/): top menu. --}}
    @if (auth()->check() && !request()->is('/'))
        <div class="lg:flex min-h-screen" x-data="{ nav: false }" @keydown.escape.window="nav = false">
            {{-- Mobile top bar --}}
            <header class="lg:hidden sticky top-0 z-30 flex items-center justify-between h-14 px-4 bg-surface border-b border-line">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-ink hover:no-underline">
                    <span class="glyph" aria-hidden="true">A</span>
                    <span class="font-semibold">Atlas ERP</span>
                </a>
                <button type="button" class="btn btn-outline btn-sm" @click="nav = true" aria-label="Open menu"
                        :aria-expanded="nav.toString()">
                    @include('partials.icon', ['name' => 'menu', 'class' => 'w-5 h-5'])
                </button>
            </header>

            <div x-show="nav" x-cloak class="fixed inset-0 z-40 bg-ink opacity-50 lg:hidden" @click="nav = false" aria-hidden="true"></div>

            <aside class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full transition-transform duration-200 ease-out
                          bg-surface border-r border-line lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:flex-shrink-0"
                   :class="{ 'translate-x-0': nav, '-translate-x-full': !nav }">
                @include('partials.sidebar')
            </aside>

            <div class="flex-1 min-w-0 flex flex-col">
                <main id="main" class="flex-1">
                    @yield('content')
                </main>

                <footer class="border-t border-line px-6 py-4 text-xs text-muted flex flex-wrap justify-between gap-2">
                    <span>Atlas ERP · fictional client system · technical case for PALO IT · Laravel {{ app()->version() }} · PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}.{{ PHP_RELEASE_VERSION }}</span>
                    <span>
                        <a class="text-muted hover:text-ink" href="{{ route('health') }}" target="_blank" rel="noopener">System status</a>
                        · <a class="text-muted hover:text-ink" href="{{ url('/') }}">Presentation</a>
                    </span>
                </footer>
            </div>
        </div>
    @else
        @include('partials.nav')

        <main id="main">
            @yield('content')
        </main>

        @include('partials.footer')
    @endif
</body>
</html>
