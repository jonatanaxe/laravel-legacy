<?php

namespace Tests\Feature;

use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtering, search, sorting and the summary aggregates.
 *
 * The point of testing this: if someone swaps `where` for a PHP `filter` or
 * forgets IndexTaskRequest's whitelist, the behavior changes silently on
 * screen. Here it breaks loudly.
 */
class TaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $user, array $attributes)
    {
        return factory(Task::class)->create(array_merge(['user_id' => $user->id], $attributes));
    }

    public function test_filters_by_status()
    {
        $ana = factory(User::class)->create();

        $this->makeTask($ana, ['title' => 'Alpha pending', 'done' => false]);
        $this->makeTask($ana, ['title' => 'Beta done', 'done' => true]);

        $this->actingAs($ana)->get('/tasks?status=open')
            ->assertStatus(200)
            ->assertSee('Alpha pending')
            ->assertDontSee('Beta done');

        $this->actingAs($ana)->get('/tasks?status=done')
            ->assertStatus(200)
            ->assertSee('Beta done')
            ->assertDontSee('Alpha pending');
    }

    public function test_overdue_filter_ignores_done_and_undated_tasks()
    {
        $ana = factory(User::class)->create();

        $this->makeTask($ana, ['title' => 'Late item', 'done' => false, 'due_date' => now()->subDay()->toDateString()]);
        $this->makeTask($ana, ['title' => 'Late item but done', 'done' => true, 'due_date' => now()->subDay()->toDateString()]);
        $this->makeTask($ana, ['title' => 'No deadline', 'done' => false, 'due_date' => null]);
        $this->makeTask($ana, ['title' => 'Future item', 'done' => false, 'due_date' => now()->addWeek()->toDateString()]);

        $this->actingAs($ana)->get('/tasks?status=overdue')
            ->assertStatus(200)
            ->assertSee('Late item')
            ->assertDontSee('Late item but done')
            ->assertDontSee('No deadline')
            ->assertDontSee('Future item');
    }

    public function test_search_by_title_or_description_escapes_wildcards()
    {
        $ana = factory(User::class)->create();

        $this->makeTask($ana, ['title' => 'Review policy', 'description' => null]);
        $this->makeTask($ana, ['title' => 'Another', 'description' => 'text about the access policy']);
        $this->makeTask($ana, ['title' => 'Unrelated', 'description' => 'another topic']);

        $this->actingAs($ana)->get('/tasks?q=policy')
            ->assertStatus(200)
            ->assertSee('Review policy')
            ->assertSee('Another')
            ->assertDontSee('Unrelated');

        // A literal '%' must not become "every record".
        $this->actingAs($ana)->get('/tasks?q=' . urlencode('%'))
            ->assertStatus(200)
            ->assertDontSee('Review policy');
    }

    public function test_sorts_by_priority_weight_not_alphabet()
    {
        $ana = factory(User::class)->create();

        $this->makeTask($ana, ['title' => 'Low task', 'priority' => 'low']);
        $this->makeTask($ana, ['title' => 'Normal task', 'priority' => 'normal']);
        $this->makeTask($ana, ['title' => 'High task', 'priority' => 'high']);

        $html = $this->actingAs($ana)->get('/tasks?sort=priority')->getContent();

        $high = strpos($html, 'High task');
        $normal = strpos($html, 'Normal task');
        $low = strpos($html, 'Low task');

        $this->assertTrue($high < $normal && $normal < $low, 'Expected order: high, normal, low');
    }

    public function test_sorts_by_due_date_with_nulls_last()
    {
        $ana = factory(User::class)->create();

        $this->makeTask($ana, ['title' => 'No deadline', 'due_date' => null]);
        $this->makeTask($ana, ['title' => 'Distant deadline', 'due_date' => now()->addMonth()->toDateString()]);
        $this->makeTask($ana, ['title' => 'Near deadline', 'due_date' => now()->addDay()->toDateString()]);

        $html = $this->actingAs($ana)->get('/tasks?sort=due')->getContent();

        $near = strpos($html, 'Near deadline');
        $distant = strpos($html, 'Distant deadline');
        $none = strpos($html, 'No deadline');

        $this->assertTrue($near < $distant && $distant < $none, 'Dated first; undated last');
    }

    public function test_invalid_parameter_is_rejected_by_the_whitelist()
    {
        $ana = factory(User::class)->create();

        // sort outside the list: the FormRequest stops it before it reaches orderByRaw.
        $this->actingAs($ana)->get('/tasks?sort=' . urlencode('id desc; drop table tasks'))
            ->assertStatus(302)
            ->assertSessionHasErrors('sort');
    }

    public function test_summary_aggregates_match_the_data()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();

        $this->makeTask($ana, ['done' => false, 'due_date' => now()->subDay()->toDateString()]); // overdue
        $this->makeTask($ana, ['done' => false, 'due_date' => null]);
        $this->makeTask($ana, ['done' => true, 'due_date' => now()->subDay()->toDateString()]);
        $this->makeTask($bruno, ['done' => false, 'due_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($ana)->get('/tasks');

        $response->assertStatus(200);

        $stats = $response->original->getData()['stats'];
        $this->assertEquals(3, $stats->total);
        $this->assertEquals(2, $stats->open_count);
        $this->assertEquals(1, $stats->done_count);
        $this->assertEquals(1, $stats->overdue_count);
    }
}
