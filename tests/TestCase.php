<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstRealDatabase();
    }

    /**
     * Most tests use RefreshDatabase, which drops and re-creates every table on
     * the configured connection. The suite normally runs on in-memory SQLite,
     * but it may also be pointed at MySQL to verify driver compatibility.
     *
     * Either way the target must be disposable. A stale config cache can point
     * the suite at the real application database and wipe it, so anything that
     * is not obviously throwaway is refused outright.
     */
    protected function guardAgainstRealDatabase(): void
    {
        $connection = config('database.default');

        if ($connection === 'sqlite') {
            if (config('database.connections.sqlite.database') !== ':memory:') {
                throw new RuntimeException(sprintf(
                    'Refusing to run tests against the SQLite database "%s". Tests require an in-memory database.',
                    config('database.connections.sqlite.database')
                ));
            }

            return;
        }

        $database = (string) config("database.connections.{$connection}.database");

        // In-memory SQLite is fine; a real driver needs a throwaway database.
        if (in_array($database, [':memory:', ''], true)) {
            return;
        }

        if (! preg_match('/(^|[_-])(test|testing)([_-]|$)/i', $database)) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against the "%s" connection using database "%s". RefreshDatabase '
                .'would wipe it. Point the suite at in-memory SQLite, or at a scratch database whose '
                .'name contains "test", then retry.',
                $connection,
                $database
            ));
        }
    }
}
