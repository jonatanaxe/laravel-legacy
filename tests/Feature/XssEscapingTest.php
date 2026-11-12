<?php

namespace Tests\Feature;

use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * XSS: a title with HTML must come out escaped on the list.
 */
class XssEscapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_title_with_script_shows_as_text()
    {
        $ana = factory(User::class)->create();

        factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($ana)->get('/tasks');

        $response->assertStatus(200);

        // The raw HTML is not in the response...
        $response->assertDontSee('<script>alert(1)</script>', false);

        // ...but the escaped text is (the browser shows it, doesn't run it).
        $response->assertSee(e('<script>alert(1)</script>'), false);
    }
}
