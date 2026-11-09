<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_isolated_postgres_database(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertSame('app_test', DB::connection()->getDatabaseName());
    }
}
