<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /health: the contract a monitor consumes.
 *
 * It also proves the part nobody tests and that always leaks: the endpoint
 * reports state without exposing secrets - and the check here is structural
 * (JSON keys), not a substring search, which false-positives on common words.
 */
class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_answers_200_with_every_check()
    {
        $response = $this->getJson('/health')->assertStatus(200);

        $this->assertEquals('ok', $response->json('status'));
        $this->assertTrue($response->json('checks.database.ok'));
        $this->assertTrue($response->json('checks.cache.ok'));
        $this->assertTrue($response->json('checks.storage.ok'));

        $this->assertNotEmpty($response->json('app.php'));
        $this->assertNotEmpty($response->json('app.laravel'));
    }

    public function test_does_not_expose_secrets_in_its_structure()
    {
        $body = $this->getJson('/health')->json();

        // The "app" block has exactly what operations needs to know.
        $this->assertEquals(
            ['name', 'env', 'php', 'laravel'],
            array_keys($body['app'])
        );

        // No value in the body may be the APP_KEY.
        $encoded = json_encode($body);
        $this->assertFalse(strpos($encoded, config('app.key')) !== false, 'APP_KEY must not appear');

        // Nor any loose credential key.
        $this->assertArrayNotHasKey('password', $body['app']);
        $this->assertArrayNotHasKey('key', $body['app']);
    }
}
