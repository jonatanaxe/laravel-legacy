<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function user()
    {
        $user = factory(User::class)->create();
        $user->forceFill(['api_token' => str_repeat('a', 60)])->save();

        return $user;
    }

    public function test_guest_goes_to_login()
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_shows_profile_and_masked_token()
    {
        $user = $this->user();

        $this->actingAs($user)->get('/account')
            ->assertStatus(200)
            ->assertSee($user->email)
            ->assertSee('aaaaaa' . str_repeat('•', 18) . 'aaaa')
            ->assertSee('/api/tasks');
    }

    public function test_new_token_invalidates_the_previous_one()
    {
        $user = $this->user();
        $old = $user->api_token;

        $this->actingAs($user)->post('/account/token')
            ->assertRedirect('/account');

        $new = $user->fresh()->api_token;

        $this->assertNotSame($old, $new);
        $this->assertSame(60, strlen($new));

        // The 'token' guard compares straight against the column: the old one
        // finds nobody anymore. A single API call - the guard caches the
        // resolved user during the test, and forgetGuards(), which would
        // clear it, only exists in newer Laravel versions.
        $this->assertFalse(User::where('api_token', $old)->exists());
        $this->getJson('/api/user', ['Authorization' => 'Bearer ' . $new])
            ->assertStatus(200)
            ->assertJson(['data' => ['email' => $user->email]]); // assertJsonPath only exists in newer versions
    }

    public function test_another_users_token_never_shows()
    {
        $ana = $this->user();
        $bruno = factory(User::class)->create();
        $bruno->forceFill(['api_token' => str_repeat('b', 60)])->save();

        $this->actingAs($ana)->get('/account')->assertDontSee(str_repeat('b', 60));
    }
}
