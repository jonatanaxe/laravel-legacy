{{--
    Heroicons v1 icon (MIT) served from disk: resources/svg/{name}.svg.
    Usage: @include('partials.icon', ['name' => 'folder', 'class' => 'w-5 h-5'])
    The name always comes from code (never from the request), and basename() blocks path traversal.
--}}
@php
    $iconFile = resource_path('svg/' . basename($name) . '.svg');
    $iconSvg = is_file($iconFile) ? file_get_contents($iconFile) : '';
    $iconSvg = str_replace('<svg ', '<svg class="' . e($class ?? 'w-5 h-5') . '" ', $iconSvg);
@endphp
{!! $iconSvg !!}
