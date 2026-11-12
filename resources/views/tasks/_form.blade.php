{{-- Partial without @extends: used by create and edit. --}}
<div class="wrap page page-narrow">
    <header class="page-head">
        <div>
            <p class="kicker">{{ $task->exists ? 'edit' : 'new task' }}</p>
            <h1>{{ $task->exists ? 'Edit task' : 'New task' }}</h1>
            <p class="sub">
                {{ $task->exists ? 'Data is validated on the server before saving.' : 'The owner comes from the session, not from the form.' }}
            </p>
        </div>
    </header>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <strong>Fix {{ $errors->count() === 1 ? 'the field below' : 'the fields below' }}:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="panel panel-pad">
        <form method="POST" class="form"
              action="{{ $task->exists ? route('tasks.update', $task) : route('tasks.store') }}">
            @csrf
            @if ($task->exists)
                @method('PUT')
                {{-- Optimistic lock: the version read by the form comes back on PUT.
                     If the row changed in the meantime, the server answers 409. --}}
                <input type="hidden" name="version" value="{{ $task->version }}">
            @endif

            <div class="field">
                <label class="label" for="project_id">Project</label>
                @if ($task->exists)
                    <p class="hint mt-0">
                        {{ $task->project ? $task->project->name : 'Personal task' }}.
                        The project is set on creation and can't be changed here.
                    </p>
                @else
                    @php
                        // Comes from the "New task" button inside a project.
                        $currentProject = old('project_id', request('project'));
                    @endphp
                    <select id="project_id" name="project_id" class="select">
                        <option value="">Personal (only I can see it)</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}"
                                @if ((string) $currentProject === (string) $project->id) selected @endif>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="hint">Only projects you can write to are listed.</p>
                    @error('project_id')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <div class="field">
                <label class="label" for="title">Title</label>
                <input id="title" name="title" type="text" maxlength="120" required
                       class="input {{ $errors->has('title') ? 'is-invalid' : '' }}"
                       value="{{ old('title', $task->title) }}"
                       @if ($errors->has('title')) aria-invalid="true" aria-describedby="title-error" @endif>
                <p class="hint">Between 3 and 120 characters.</p>
                @error('title')
                    <p class="field-error" id="title-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="2000"
                          class="textarea {{ $errors->has('description') ? 'is-invalid' : '' }}"
                          @if ($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $task->description) }}</textarea>
                <p class="hint">Optional, up to 2000 characters.</p>
                @error('description')
                    <p class="field-error" id="description-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="label" for="priority">Priority</label>
                    @php $currentPriority = old('priority', $task->priority ?: 'normal'); @endphp
                    <select id="priority" name="priority" class="select">
                        <option value="low" @if ($currentPriority === 'low') selected @endif>Low</option>
                        <option value="normal" @if ($currentPriority === 'normal') selected @endif>Normal</option>
                        <option value="high" @if ($currentPriority === 'high') selected @endif>High</option>
                    </select>
                    <p class="hint">Sorts the list and colors the badge.</p>
                    @error('priority')
                        <p class="field-error" id="priority-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="due_date">Due date</label>
                    <input id="due_date" name="due_date" type="date"
                           class="input {{ $errors->has('due_date') ? 'is-invalid' : '' }}"
                           value="{{ old('due_date', $task->due_date ? $task->due_date->format('Y-m-d') : '') }}"
                           @if ($errors->has('due_date')) aria-invalid="true" aria-describedby="due_date-error" @endif>
                    <p class="hint">Optional. Past due and still open = overdue.</p>
                    @error('due_date')
                        <p class="field-error" id="due_date-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="field">
                <label class="label" for="status">Stage</label>
                @php $currentStatus = old('status', $task->status ?: 'backlog'); @endphp
                <select id="status" name="status" class="select">
                    @foreach (\App\Task::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @if ($currentStatus === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="hint">"Done" still feeds the counter and the Done filter. The stage is not a loose field.</p>
                @error('status')
                    <p class="field-error" id="status-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save</button>
                <a class="btn btn-ghost" href="{{ route('tasks.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
