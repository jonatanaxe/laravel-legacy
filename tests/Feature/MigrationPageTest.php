<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /migration: the page existed (controller, view, Overview) but no route
 * pointed to it, and the view called route('concepts'), which never existed.
 * Rendering the whole page here is what catches that kind of breakage: a
 * missing route() only blows up when the Blade is compiled and executed.
 */
class MigrationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_overview()
    {
        $this->get('/migration')
            ->assertStatus(200)
            ->assertSee('next step')
            ->assertSee('PHP ' . PHP_VERSION);
    }

    public function test_logged_in_user_sees_it_and_the_menu_links_to_it()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->get('/migration')
            ->assertStatus(200)
            ->assertSee(route('migration.index'));
    }

    public function test_concepts_link_points_to_the_home_anchor()
    {
        $this->get('/migration')->assertSee(url('/') . '#concepts');
    }
}
