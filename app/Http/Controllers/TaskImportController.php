<?php

namespace App\Http\Controllers;

use App\Jobs\ImportTasksCsv;
use App\TaskImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CSV import: fast upload, queued processing, per-line report.
 *
 * The controller doesn't read the file. It validates the upload, stores the
 * file under a server-generated name and dispatches the job. So the POST
 * returns in milliseconds even with 5000 lines.
 */
class TaskImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // Always through the user's relationship: someone else's id in the URL doesn't exist.
        $imports = auth()->user()->taskImports()->latest('id')->paginate(10);

        return view('imports.index', compact('imports'));
    }

    public function store(Request $request)
    {
        // mimes:csv,txt - the file's real type is what counts. An .xlsx
        // renamed to .csv is rejected because the content isn't CSV text.
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt',
        ]);

        $file = $request->file('file');

        // Sanitized display name (same care as attachments): slashes, line
        // breaks and control characters removed.
        $original = preg_replace('/[^\pL\pN\s._-]+/u', '_', basename($file->getClientOriginalName()));
        $original = mb_substr($original !== '' ? $original : 'import.csv', 0, 190);

        // Private disk, generated name: nginx doesn't serve the file and it
        // can't collide with another user's upload.
        $path = $file->store('imports/' . $request->user()->id, 'local');

        $import = new TaskImport(['original_name' => $original]);
        $import->user_id = $request->user()->id;
        $import->status = 'pending';
        $import->save();

        // In production (QUEUE_CONNECTION=database/redis) this returns right
        // away and the worker processes it. Here the driver is sync, so the
        // job runs inline - the job code is identical in both.
        ImportTasksCsv::dispatch($import, $path);

        return redirect()->route('imports.index')
            ->with('status', 'Import received. The report appears below.');
    }

    public function show($id)
    {
        // Scoped to the user: someone else's id becomes 404, not 403 - it
        // doesn't even confirm that report exists.
        $import = auth()->user()->taskImports()->findOrFail($id);

        return view('imports.show', compact('import'));
    }

    public function destroy($id)
    {
        $import = auth()->user()->taskImports()->findOrFail($id);

        // Deletes the report, never the tasks already imported: undoing an
        // import is a business decision, not an "undo" button.
        $import->delete();

        return redirect()->route('imports.index')->with('status', 'Report removed.');
    }
}
