<?php

namespace App\Http\Controllers;

use App\Task;
use App\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment upload and download.
 *
 * Three decisions that shut down known attacks:
 *  1. authorization on the TASK (TaskPolicy), not on "is logged in".
 *     Without it, swapping /attachments/7/download for /attachments/8/download
 *     leaks someone else's file (IDOR);
 *  2. a server-generated name (hash), never the uploaded name. Path traversal
 *     and double extensions ("invoice.pdf.php") stop having any effect;
 *  3. a private local disk (storage/app), served by an authenticated route.
 *     nginx never executes or serves the file directly.
 */
class TaskAttachmentController extends Controller
{
    /** Per-task limit: keeps repeated uploads from filling the disk. */
    const MAX_PER_TASK = 10;

    const ALLOWED_MIMES = 'pdf,png,jpg,jpeg,txt,csv';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, Task $task)
    {
        // Whoever can edit the task can attach. Viewers can't.
        $this->authorize('update', $task);

        if ($task->attachments()->count() >= self::MAX_PER_TASK) {
            return back()->withErrors([
                'file' => 'Limit of ' . self::MAX_PER_TASK . ' attachments per task.',
            ]);
        }

        // mimes validates the CONTENT (extension derived from the real mime),
        // not just the name. max is in KB. Both checked on the server.
        $request->validate([
            'file' => 'required|file|max:5120|mimes:' . self::ALLOWED_MIMES,
        ]);

        $file = $request->file('file');

        // Sanitized display name: strips line breaks and control characters
        // that could become header injection on download.
        $original = preg_replace('/[^\pL\pN\s._-]+/u', '_', basename($file->getClientOriginalName()));
        $original = mb_substr($original !== '' ? $original : 'file', 0, 190);

        // store() generates a random name and returns the path relative to the disk.
        $path = $file->store('attachments/' . $task->id, 'local');

        $attachment = new TaskAttachment(['original_name' => $original]);
        $attachment->path = $path;
        $attachment->mime = $file->getClientMimeType();
        $attachment->size = $file->getSize();
        $attachment->task_id = $task->id;
        $attachment->user_id = $request->user()->id;
        $attachment->save();

        return back()->with('status', 'Attachment uploaded.');
    }

    public function download(TaskAttachment $attachment)
    {
        // The task's policy applies: an attachment inherits the task's visibility.
        $this->authorize('view', $attachment->task);

        $disk = Storage::disk('local');

        if (!$disk->exists($attachment->path)) {
            abort(404, 'File not found in storage.');
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(TaskAttachment $attachment)
    {
        $this->authorize('update', $attachment->task);

        // Only the uploader or a project admin may remove? Here the rule is
        // the task's: whoever can write, manages.
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }
}
