<?php

namespace Tests\Feature;

use App\Project;
use App\Task;
use App\TaskAttachment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Attachments: validated upload, authorized download, private disk.
 *
 * Storage::fake('local') swaps the real disk for a temporary one: the test
 * doesn't write to the real storage/app and can assert "file exists" /
 * "file was deleted" without depending on prior state.
 */
class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function projectTask(User $owner, array $members = [])
    {
        $project = new Project(['name' => $owner->name . '\'s project']);
        $project->user_id = $owner->id;
        $project->save();
        $project->users()->attach($owner->id, ['role' => 'owner']);

        foreach ($members as $member) {
            $project->users()->attach($member[0]->id, ['role' => $member[1]]);
        }

        $task = factory(Task::class)->create(['user_id' => $owner->id]);
        $task->project_id = $project->id;
        $task->save();

        return $task;
    }

    private function attach(Task $task, User $user, $name = 'note.txt')
    {
        Storage::disk('local')->put('attachments/' . $task->id . '/seed.txt', 'content');

        $attachment = new TaskAttachment(['original_name' => $name]);
        $attachment->path = 'attachments/' . $task->id . '/seed.txt';
        $attachment->mime = 'text/plain';
        $attachment->size = 8;
        $attachment->task_id = $task->id;
        $attachment->user_id = $user->id;
        $attachment->save();

        return $attachment;
    }

    public function test_owner_uploads_and_file_stays_outside_the_public_folder()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = TaskAttachment::where('task_id', $task->id)->firstOrFail();

        $this->assertSame('contract.pdf', $attachment->original_name);
        $this->assertSame($ana->id, $attachment->user_id);
        $this->assertSame(120 * 1024, $attachment->size);
        Storage::disk('local')->assertExists($attachment->path);

        // The path is generated: it doesn't contain the uploaded name.
        $this->assertSame(false, strpos($attachment->path, 'contrato'));
    }

    public function test_executable_file_is_rejected_by_its_real_type()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->from('/tasks/' . $task->id)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            ])
            ->assertRedirect('/tasks/' . $task->id)
            ->assertSessionHasErrors('file');

        $this->assertSame(0, TaskAttachment::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_file_over_the_limit_is_rejected()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->from('/tasks/' . $task->id)
            ->post(route('tasks.attachments.store', $task), [
                // 6 MB: the limit is 5 MB (5120 KB).
                'file' => UploadedFile::fake()->create('large.pdf', 6144, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, TaskAttachment::count());
    }

    public function test_who_cannot_edit_the_task_cannot_attach()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($bruno)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('x.txt', 1, 'text/plain'),
            ])
            ->assertStatus(403);

        $this->assertSame(0, TaskAttachment::count());
    }

    public function test_project_viewer_cannot_attach()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $carla = factory(User::class)->create();
        $task = $this->projectTask($ana, [[$carla, 'viewer']]);

        $this->actingAs($carla)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('x.txt', 1, 'text/plain'),
            ])
            ->assertStatus(403);

        $this->assertSame(0, TaskAttachment::count());
    }

    public function test_download_requires_authorization_on_the_task()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);
        $attachment = $this->attach($task, $ana);

        // The owner downloads.
        $response = $this->actingAs($ana)->get(route('attachments.download', $attachment));
        $response->assertStatus(200);
        $disposition = $response->headers->get('content-disposition');
        $this->assertTrue(strpos($disposition, 'attachment') !== false);
        $this->assertTrue(strpos($disposition, 'note.txt') !== false);

        // A third party doesn't: the ID in the URL is not authorization.
        $this->actingAs($bruno)
            ->get(route('attachments.download', $attachment))
            ->assertStatus(403);
    }

    public function test_project_member_downloads_a_shared_tasks_attachment()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $task = $this->projectTask($ana, [[$bruno, 'member']]);
        $attachment = $this->attach($task, $ana);

        $this->actingAs($bruno)
            ->get(route('attachments.download', $attachment))
            ->assertStatus(200);
    }

    public function test_guest_cannot_download_an_attachment()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);
        $attachment = $this->attach($task, $ana);

        $this->get(route('attachments.download', $attachment))->assertRedirect('/login');
    }

    public function test_directory_traversal_name_is_flattened()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        $this->actingAs($ana)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('../../etc/passwd.txt', 1, 'text/plain'),
            ]);

        $attachment = TaskAttachment::where('task_id', $task->id)->firstOrFail();

        // basename strips the path; the file on disk has a random name.
        $this->assertSame('passwd.txt', $attachment->original_name);
        $this->assertSame(false, strpos($attachment->path, '..'));
    }

    public function test_remove_deletes_from_disk_and_database()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);
        $attachment = $this->attach($task, $ana);

        $this->actingAs($ana)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_attachment_limit_per_task()
    {
        Storage::fake('local');

        $ana = factory(User::class)->create();
        $task = factory(Task::class)->create(['user_id' => $ana->id]);

        for ($i = 1; $i <= 10; $i++) {
            $this->attach($task, $ana, 'file-' . $i . '.txt');
        }

        $this->actingAs($ana)
            ->from('/tasks/' . $task->id)
            ->post(route('tasks.attachments.store', $task), [
                'file' => UploadedFile::fake()->create('eleven.txt', 1, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(10, TaskAttachment::count());
    }
}
