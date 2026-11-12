<?php

namespace Tests\Feature;

use App\Project;
use App\Task;
use App\TaskImport;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CSV import.
 *
 * The risk of a bulk import is not "the file is big": it is the file trying
 * to choose an owner (user_id) or a project (project_id). The tests below
 * cover both attempts, plus per-line validation and report isolation
 * between users.
 */
class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv($content)
    {
        // createWithContent() doesn't exist in 5.8: the file is built on disk
        // and passed as a test upload.
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'tasks.csv', 'text/csv', filesize($path), null, true);
    }

    private function import(User $user, $content)
    {
        return $this->actingAs($user)->post(route('imports.store'), [
            'file' => $this->csv($content),
        ]);
    }

    public function test_imports_csv_with_header()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->import($ana, "title,description,priority,due_date,done\n"
            . "Review contract,contract text,high,2030-01-10,no\n"
            . "Close spreadsheet,,low,,yes\n")
            ->assertRedirect(route('imports.index'));

        $this->assertSame(2, Task::where('user_id', $ana->id)->count());

        $task = Task::where('title', 'Review contract')->firstOrFail();

        $this->assertSame($ana->id, $task->user_id);
        $this->assertSame('high', $task->priority);
        $this->assertSame('2030-01-10', $task->due_date->toDateString());
        $this->assertFalse($task->done);
        $this->assertNull($task->project_id);

        $this->assertTrue(Task::where('title', 'Close spreadsheet')->firstOrFail()->done);
    }

    public function test_imports_headerless_csv_in_fixed_order()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->import($ana, "Positional task,,normal,,0\n");

        $task = Task::where('title', 'Positional task')->firstOrFail();

        $this->assertSame('normal', $task->priority);
        $this->assertFalse($task->done);
    }

    public function test_invalid_line_does_not_sink_the_whole_file()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->import($ana, "title,description,priority,due_date,done\n"
            . "Task ok,valid,,,\n"
            . "ab,short,,,\n"                            // 2-character title
            . "bad date,,,31/12/2030,\n"                 // wrong format
            . "invalid priority,,urgent,,\n"            // outside the whitelist
            . "another ok,,,,\n");

        $this->assertSame(2, Task::where('user_id', $ana->id)->count());

        $import = TaskImport::where('user_id', $ana->id)->firstOrFail();

        $this->assertSame(5, $import->total_rows);
        $this->assertSame(2, $import->imported_rows);
        $this->assertSame(3, $import->failed_rows);
        $this->assertSame('done', $import->status);

        $errors = $import->errorsList();

        // File line, counting the header: 3, 4 and 5.
        $this->assertSame([3, 4, 5], array_column($errors, 'line'));
    }

    public function test_user_id_column_in_the_file_is_ignored()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        // Classic attempt: "user_id" in the spreadsheet to create a task in
        // someone else's account. It is neither in $fillable nor in the column map.
        $this->import($ana, "title,user_id,description\n"
            . "Planted in bruno's account,{$bruno->id},attempt\n");

        $task = Task::where('title', 'Planted in bruno\'s account')->firstOrFail();

        $this->assertSame($ana->id, $task->user_id);
        $this->assertSame(0, Task::where('user_id', $bruno->id)->count());
    }

    public function test_project_id_column_in_the_file_is_ignored()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $project = new Project(['name' => 'Bruno\'s project']);
        $project->user_id = $bruno->id;
        $project->save();

        $this->import($ana, "title,project_id\n"
            . "Planted task,{$project->id}\n");

        $task = Task::where('title', 'Planted task')->firstOrFail();

        // Ana's personal task: it didn't get into someone else's project.
        $this->assertNull($task->project_id);
        $this->assertSame($ana->id, $task->user_id);
    }

    public function test_header_is_case_insensitive_and_order_free()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->import($ana, "Done,Priority,Due_Date,Title,Description\n"
            . "yes,High,2030-02-01,Reordered header,any column order\n");

        $task = Task::where('title', 'Reordered header')->firstOrFail();

        $this->assertSame('high', $task->priority);
        $this->assertSame('2030-02-01', $task->due_date->toDateString());
        $this->assertTrue($task->done);
    }

    public function test_non_csv_file_is_rejected()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->actingAs($ana)
            ->from(route('imports.index'))
            ->post(route('imports.store'), [
                'file' => UploadedFile::fake()->create('spreadsheet.xlsx', 10,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, TaskImport::count());
        $this->assertSame(0, Task::count());
    }

    public function test_another_users_report_does_not_open()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $this->import($ana, "title\nAna's task\n");

        $import = TaskImport::where('user_id', $ana->id)->firstOrFail();

        // 404, not 403: it doesn't confirm the report exists in Ana's account.
        $this->actingAs($bruno)->get(route('imports.show', $import->id))->assertStatus(404);

        $this->actingAs($bruno)
            ->delete(route('imports.destroy', $import->id))
            ->assertStatus(404);

        $this->assertDatabaseHas('task_imports', ['id' => $import->id]);
    }

    public function test_import_list_belongs_to_the_user()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $this->import($ana, "title\nAna's report\n");
        $this->import($bruno, "title\nBruno's report\n");

        $this->actingAs($ana)
            ->get(route('imports.index'))
            ->assertStatus(200)
            ->assertSee('tasks.csv');

        // Each one sees only their row: 1 import per user.
        $this->assertSame(1, TaskImport::where('user_id', $ana->id)->count());
        $this->assertSame(1, TaskImport::where('user_id', $bruno->id)->count());
    }

    public function test_blank_line_does_not_count_as_an_error()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        // Exported spreadsheets often end with ";;;;" and an empty line.
        $this->import($ana, "title,description,priority,due_date,done\n"
            . "First,,,,\n"
            . ";;;;\n"
            . "\n"
            . "Second,,,,\n");

        $import = TaskImport::where('user_id', $ana->id)->firstOrFail();

        $this->assertSame(2, $import->total_rows);
        $this->assertSame(2, $import->imported_rows);
        $this->assertSame(0, $import->failed_rows);
    }

    public function test_file_is_deleted_after_processing()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();

        $this->import($ana, "title\nAny task\n");

        // No client data left sitting on disk after processing.
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_guest_cannot_import()
    {
        $this->get(route('imports.index'))->assertRedirect('/login');
        $this->post(route('imports.store'), [])->assertRedirect('/login');
    }
}
