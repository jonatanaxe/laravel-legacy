{{-- Partial without @extends: used by create and edit. --}}
<div class="wrap page page-narrow">
    <header class="page-head">
        <div>
            <p class="kicker">{{ $project->exists ? 'edit' : 'new project' }}</p>
            <h1>{{ $project->exists ? 'Edit project' : 'New project' }}</h1>
            <p class="sub">
                You join as the owner. Then invite the team from the project page,
                as members or viewers.
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
              action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}">
            @csrf
            @if ($project->exists)
                @method('PUT')
            @endif

            <div class="field">
                <label class="label" for="name">Name</label>
                <input id="name" name="name" type="text" maxlength="120" required
                       class="input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                       value="{{ old('name', $project->name) }}">
                <p class="hint">Between 3 and 120 characters.</p>
                @error('name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="2000"
                          class="textarea {{ $errors->has('description') ? 'is-invalid' : '' }}">{{ old('description', $project->description) }}</textarea>
                <p class="hint">Optional, up to 2000 characters.</p>
                @error('description')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save</button>
                <a class="btn btn-ghost" href="{{ route('projects.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
