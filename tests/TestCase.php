<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Refuses to rebuild anything but the in-memory test database.
     *
     * RefreshDatabase runs migrate:fresh on whatever connection is
     * configured. If the environment leaks a real DB_CONNECTION into the
     * suite, that is the demo database being wiped. Failing loudly here is
     * cheaper than finding out from an empty dashboard.
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class]) || isset($uses[DatabaseMigrations::class])) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            if ($connection !== 'sqlite' || $database !== ':memory:') {
                throw new \RuntimeException(sprintf(
                    'Refusing to refresh "%s" (%s): tests only rebuild the in-memory SQLite database.',
                    $connection,
                    $database
                ));
            }
        }

        return parent::setUpTraits();
    }
}
