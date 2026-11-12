<?php

namespace Tests\Unit;

use App\Migration\Evidence;
use Tests\TestCase;

class EvidenceTest extends TestCase
{
    private $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/evidence-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_without_a_snapshot_nothing_is_claimed()
    {
        $this->assertNull((new Evidence($this->path))->measured());
    }

    public function test_counts_come_from_the_snapshot()
    {
        file_put_contents($this->path, json_encode([
            'blockers' => [['name' => 'a'], ['name' => 'b']],
            'suites' => [
                ['key' => 'kernel', 'ok' => true, 'tests' => 150, 'assertions' => 509, 'command' => 'vendor/bin/phpunit'],
                ['key' => 'http', 'ok' => true, 'tests' => 33, 'assertions' => 170, 'command' => 'php artisan lab:verify'],
            ],
        ]));

        $evidence = (new Evidence($this->path))->measured();

        $this->assertSame(183, $evidence['tests']);
        $this->assertSame(33, $evidence['suites']['http']['tests']);
        $this->assertSame(2, $evidence['blockers']);
    }

    public function test_a_snapshot_without_suites_is_not_evidence()
    {
        file_put_contents($this->path, json_encode(['blockers' => []]));

        $this->assertNull((new Evidence($this->path))->measured());
    }
}
