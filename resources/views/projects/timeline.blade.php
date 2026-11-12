@extends('layouts.app')

@section('title', $project->name . ' · Schedule')

{{--
    Schedule (Gantt): each task runs from creation to due date.
    List and bars share the SAME grid row - they used to be two columns with
    different heights, and the bar didn't line up with the title.
    No onclick: the whole row is an <a>, which works with the CSP and keyboard.
--}}
@section('content')
    @php
        $zooms = ['day' => 'Day', 'week' => 'Week', 'month' => 'Quarter'];
        $barColor = [
            'backlog' => 'bg-stage-backlog',
            'doing' => 'bg-stage-doing',
            'review' => 'bg-stage-review',
            'done' => 'bg-stage-done',
        ];
        $snapshot = $flow['snapshot'];
    @endphp

    <div class="wrap page">
        @include('projects._header', ['tab' => 'schedule'])

        <section class="panel mt-6 overflow-hidden" aria-labelledby="schedule-title">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-line">
                <div>
                    <h2 class="panel-title" id="schedule-title">Schedule</h2>
                    <p class="text-sm text-muted mt-1">
                        {{ $scaleStart->format('M j') }} to {{ $scaleEnd->format('M j') }} ·
                        {{ count($tasks) }} tasks in this window
                        @if ($hidden)
                            · {{ $hidden }} outside it
                        @endif
                        · project {{ $snapshot['percent'] }}% done
                    </p>
                </div>

                <nav class="inline-flex rounded-md border border-line-strong overflow-hidden" aria-label="Scale">
                    @foreach ($zooms as $key => $label)
                        <a href="{{ route('projects.timeline', [$project, 'zoom' => $key]) }}"
                           class="px-3 py-1 text-sm hover:no-underline {{ $loop->first ? '' : 'border-l border-line-strong' }}
                                  {{ $zoom === $key ? 'bg-brand text-white font-semibold' : 'bg-surface text-body hover:bg-surface-2' }}"
                           @if ($zoom === $key) aria-current="true" @endif>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            @if (count($tasks))
                <div class="overflow-x-auto">
                    <div style="min-width: 760px">
                        {{-- Scale --}}
                        <div class="flex border-b border-line bg-surface-2">
                            <div class="w-64 flex-shrink-0 px-5 py-2 text-xs font-semibold text-muted">Task</div>
                            <div class="relative flex-1 h-8">
                                @foreach ($scalePoints as $point)
                                    <span class="absolute top-0 h-full pl-1 border-l border-line text-xs text-muted font-mono flex items-center"
                                          style="left: {{ $point['left'] }}%">{{ $point['label'] }}</span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Rows --}}
                        @foreach ($tasks as $task)
                            <a href="{{ route('tasks.show', $task['id']) }}"
                               class="flex items-center border-b border-line hover:bg-surface-2 hover:no-underline"
                               title="{{ $task['title'] }} ({{ $task['status_label'] }}){{ $task['due'] ? ', due ' . $task['due'] : '' }}">
                                <div class="w-64 flex-shrink-0 px-5 py-2 min-w-0">
                                    <p class="text-sm truncate {{ $task['status'] === 'done' ? 'text-muted line-through' : 'text-ink' }}">
                                        {{ $task['title'] }}
                                    </p>
                                    <p class="text-xs text-muted truncate">
                                        {{ $task['owner'] }}
                                        @if ($task['due'])
                                            · <span class="{{ $task['overdue'] ? 'text-danger font-semibold' : '' }}">due {{ $task['due'] }}</span>
                                        @endif
                                    </p>
                                </div>

                                <div class="relative flex-1 h-12">
                                    <span class="absolute top-0 h-full w-px bg-danger" style="left: {{ $todayLeft }}%" aria-hidden="true"></span>
                                    <span class="absolute h-3 {{ $task['overdue'] ? 'bg-danger' : ($barColor[$task['status']] ?? 'bg-stage-backlog') }}
                                                 {{ $task['cut_start'] ? '' : 'rounded-l-full' }} {{ $task['cut_end'] ? '' : 'rounded-r-full' }}"
                                          style="top: 18px; left: {{ $task['left'] }}%; width: {{ $task['width'] }}%"></span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="px-5 py-8 text-body">
                    No tasks in this window.
                    @if ($hidden)
                        {{ $hidden }} are outside it: try the Quarter scale.
                    @endif
                </p>
            @endif

            <ul class="flex flex-wrap gap-4 px-5 py-3 border-t border-line text-sm text-body list-none m-0">
                @foreach (\App\Task::statusLabels() as $key => $label)
                    <li class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded-full {{ $barColor[$key] }}"></span>{{ $label }}</li>
                @endforeach
                <li class="flex items-center gap-2"><span class="inline-block w-4 h-2 rounded-full bg-danger"></span>overdue</li>
                <li class="flex items-center gap-2"><span class="inline-block w-px h-4 bg-danger"></span>today</li>
            </ul>
        </section>

        <p class="sub mt-4">Each bar runs from the task's creation to its due date. Without a due date it covers only the day it was created.</p>
    </div>
@endsection
