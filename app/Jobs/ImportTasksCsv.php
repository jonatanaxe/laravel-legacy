<?php

namespace App\Jobs;

use App\Task;
use App\TaskImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Queued CSV import.
 *
 * Why a queue and not the request: 5000 lines inside a POST hold a PHP-FPM
 * worker, blow max_execution_time and leave the user staring at a spinner.
 * On the queue the request ends in milliseconds and the report fills in as
 * it goes.
 *
 * `$tries = 1`: an import is NOT idempotent. Retrying after a failure
 * halfway would duplicate the rows already written. If it failed, it failed
 * - the report shows how far it got and the user resends only what's left.
 *
 * Streamed reading (fgetcsv), never file_get_contents: a 50 MB file can't
 * become 50 MB of memory just because a column is text.
 */
class ImportTasksCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    private $import;

    private $path;

    /**
     * Accepted columns. Any other column in the file (including user_id and
     * project_id, if someone decides to send them) is ignored: the owner comes
     * from the session and the project is not set by a CSV.
     */
    const COLUMNS = ['title', 'description', 'priority', 'due_date', 'done'];

    public function __construct(TaskImport $import, $path)
    {
        $this->import = $import;
        $this->path = $path;
    }

    public function handle()
    {
        $disk = Storage::disk('local');

        $this->import->status = 'processing';
        $this->import->save();

        $stream = $disk->readStream($this->path);

        if ($stream === false || $stream === null) {
            $this->fail();

            return;
        }

        $header = null;
        $total = 0;
        $imported = 0;
        $errors = [];
        $line = 0;

        while (($row = fgetcsv($stream, 0, ',')) !== false) {
            $line++;

            // A blank line (a spreadsheet exporting ";;;;" or ";;;") is not an
            // error: it isn't data, it's file noise.
            if ($this->isBlank($row)) {
                continue;
            }

            $row = array_map([$this, 'clean'], $row);

            if ($this->isBlank($row)) {
                continue;
            }

            if ($header === null) {
                $header = $this->header($row);

                if ($header !== null) {
                    continue; // it was the header, not data
                }
            }

            $total++;

            if ($total > TaskImport::MAX_ROWS) {
                // Stop reading: the report says where it stopped. Better to refuse
                // the rest than take the worker down with a timeout halfway.
                break;
            }

            $data = $this->toArray($row, $header);
            $validator = Validator::make($data, [
                'title' => 'required|string|min:3|max:120',
                'description' => 'nullable|string|max:2000',
                'priority' => 'nullable|in:low,normal,high',
                'due_date' => 'nullable|date_format:Y-m-d',
                'done' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            $task = new Task($validator->validated());

            // Server defaults: an optional CSV column becomes NULL, and
            // tasks.priority/done are NOT NULL. Same rule as the form.
            if (empty($task->priority)) {
                $task->priority = 'normal';
            }

            $task->done = (bool) $task->done;

            // The owner comes from the import (which came from the session), never the file.
            DB::transaction(function () use ($task) {
                $this->import->user->tasks()->save($task);
            });

            $imported++;
        }

        fclose($stream);

        $this->finish($total, $imported, $errors);

        // File consumed: it doesn't stay on disk holding client data.
        $disk->delete($this->path);
    }

    /**
     * Definitive job failure (file gone, disk down). The user needs to know
     * their import died, not find out by its absence.
     */
    public function failed()
    {
        $this->import->status = 'failed';
        $this->import->errors = json_encode([[
            'line' => 0,
            'message' => 'The import failed during processing. Please upload the file again.',
        ]]);
        $this->import->save();
    }

    private function finish($total, $imported, array $errors)
    {
        $this->import->total_rows = $total;
        $this->import->imported_rows = $imported;
        $this->import->failed_rows = count($errors);
        $this->import->status = 'done';

        if (!empty($errors)) {
            // Error cap: a report with 5000 error lines helps no one and grows
            // the column without limit.
            $this->import->errors = json_encode(
                array_slice($errors, 0, TaskImport::MAX_ERRORS)
            );
        }

        $this->import->save();
    }

    /**
     * Normalizes a cell: no BOM, no stray whitespace, no control characters
     * coming from a spreadsheet.
     */
    private function clean($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }

    /**
     * A row with no useful content. Covers an empty \n, Excel's ";;;;" and a
     * lone ";", without mistaking a real title (which has a letter or digit).
     */
    private function isBlank(array $row)
    {
        $joined = preg_replace('/[\s;,]+/u', '', implode('', $row));

        return $joined === '';
    }

    /**
     * Column => index map if the first line is a header; null if it is data.
     * Header names are case-insensitive and may come in any order.
     */
    private function header(array $row)
    {
        $map = [];

        foreach ($row as $index => $cell) {
            $key = $this->clean(mb_strtolower($cell, 'UTF-8'));

            if (in_array($key, self::COLUMNS, true)) {
                $map[$key] = $index;
            }
        }

        // No known column at all: the row is positional data.
        return $map === [] ? null : $map;
    }

    private function toArray(array $row, $header)
    {
        if ($header === null) {
            // No header: fixed order title, description, priority,
            // due_date, done. Extra trailing columns are ignored.
            $header = array_flip(self::COLUMNS);
        }

        $data = [];

        foreach (self::COLUMNS as $column) {
            $index = isset($header[$column]) ? $header[$column] : null;
            $value = $index !== null && isset($row[$index]) ? $row[$index] : null;

            if ($value === '') {
                $value = null;
            }

            if ($column === 'priority' && $value !== null) {
                $value = mb_strtolower($value, 'UTF-8');
            }

            if ($column === 'done' && $value !== null) {
                // Spreadsheets write "yes", "1", "true", "x". Normalize the
                // common cases; anything else becomes 0, not an error.
                $word = mb_strtolower($value, 'UTF-8');

                if (in_array($word, ['yes', 'y', 'x', 'done'], true)) {
                    $value = 1;
                } elseif (in_array($word, ['no', 'n', 'open'], true)) {
                    $value = 0;
                } else {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                }
            }

            $data[$column] = $value;
        }

        return $data;
    }
}
