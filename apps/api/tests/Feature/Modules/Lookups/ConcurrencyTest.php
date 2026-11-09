<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Lookups;

use App\Modules\Identity\Contracts\RequesterDirectory;
use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Lookups\Contracts\EnrichmentRequest;
use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\RequestEnrichment;
use App\Modules\Lookups\Contracts\RunLookup;
use App\Modules\Lookups\Domain\Models\IdempotencyKey;
use App\Modules\Lookups\Domain\Models\LookupQueueEntry;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Quota\Domain\Models\QuotaAllocation;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Real parallelism: N forked processes, each with its own Postgres connection, released at the same instant.
 * N stays below Postgres' default max_connections (100); scripts/load-test.sh drives ~200 over HTTP.
 */
final class ConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private const int WORKERS = 60;

    private const int QUOTA = 10;

    /** @var list<string> */
    private array $analystIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the concurrency test.');
        }
        config(['enrichment.default_daily_quota' => self::QUOTA]);
        Provider::factory()->create(['slug' => 'mock-intel']);
        $this->analystIds = array_values(array_map(strval(...), Analyst::factory()->count(3)->create()->modelKeys()));
    }

    /** Children commit real rows; wipe them so later transactional tests start clean. */
    protected function tearDown(): void
    {
        $tables = array_filter(
            array_column(DB::select("select tablename from pg_tables where schemaname = 'public'"), 'tablename'),
            static fn (mixed $t): bool => is_string($t) && $t !== 'migrations',
        );
        DB::statement('TRUNCATE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
        parent::tearDown();
    }

    public function test_never_leases_more_than_the_quota_and_queues_the_rest_fifo(): void
    {
        $results = $this->inParallel(fn (int $i) => $this->request("host-{$i}.example.com", "key-{$i}"));

        self::assertSame(array_fill(0, self::WORKERS, 'ok'), $results);
        self::assertSame(self::QUOTA, LookupRequest::query()->where('status', LookupStatus::Leased)->count());
        self::assertSame(self::WORKERS - self::QUOTA, LookupRequest::query()->where('status', LookupStatus::Queued)->count());
        self::assertSame(self::WORKERS - self::QUOTA, LookupQueueEntry::query()->count());
        $quota = QuotaAllocation::query()->sole();
        self::assertSame([self::QUOTA, 0], [$quota->reserved, $quota->consumed]);
    }

    public function test_same_indicator_from_many_clients_creates_one_lookup_and_spends_one_unit(): void
    {
        $results = $this->inParallel(fn (int $i) => $this->request('evil.example.com', "key-{$i}"));

        self::assertSame(array_fill(0, self::WORKERS, 'ok'), $results);
        self::assertSame(1, LookupRequest::query()->count());
        self::assertSame(self::WORKERS, IdempotencyKey::query()->count());
        self::assertSame(1, IdempotencyKey::query()->where('deduplicated', false)->count());
        self::assertSame(1, QuotaAllocation::query()->sole()->reserved);
    }

    public function test_concurrent_retries_with_one_key_are_idempotent(): void
    {
        $results = $this->inParallel(fn (int $i) => $this->request('evil.example.com', 'same-key', analyst: 0));

        self::assertSame(array_fill(0, self::WORKERS, 'ok'), $results);
        self::assertSame(1, LookupRequest::query()->count());
        self::assertSame(1, IdempotencyKey::query()->count());
        self::assertSame(1, QuotaAllocation::query()->sole()->reserved);
    }

    public function test_concurrent_runs_consume_one_unit(): void
    {
        $this->request('evil.example.com', 'k', analyst: 0);
        $lookupId = LookupRequest::query()->sole()->id;

        $results = $this->inParallel(function () use ($lookupId): void {
            $requester = $this->app->make(RequesterDirectory::class)->find($this->analystIds[0]);
            self::assertNotNull($requester);
            $this->app->make(RunLookup::class)->run($lookupId, $requester);
        });

        self::assertSame(array_fill(0, self::WORKERS, 'ok'), $results);
        $quota = QuotaAllocation::query()->sole();
        self::assertSame([0, 1], [$quota->reserved, $quota->consumed]);
    }

    private function request(string $indicator, string $key, ?int $analyst = null): void
    {
        $requester = $this->app->make(RequesterDirectory::class)
            ->find($this->analystIds[$analyst ?? random_int(0, 2)]);
        self::assertNotNull($requester);

        $this->app->make(RequestEnrichment::class)->request(new EnrichmentRequest($requester, $key, $indicator));
    }

    /**
     * Forks WORKERS children that start together; returns 'ok' or the error message per child.
     *
     * @param  callable(int): void  $work
     * @return list<string>
     */
    private function inParallel(callable $work): array
    {
        DB::disconnect(); // children must not share the parent's socket
        $dir = sys_get_temp_dir().'/concurrency-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $startAt = microtime(true) + 1.0;
        $pids = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                time_sleep_until($startAt);
                try {
                    $work($i);
                    file_put_contents("{$dir}/{$i}", 'ok');
                } catch (Throwable $e) {
                    file_put_contents("{$dir}/{$i}", $e::class.': '.$e->getMessage());
                }
                posix_kill(posix_getpid(), SIGKILL); // skip PHPUnit/Laravel shutdown handlers in the child
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $results[] = (string) @file_get_contents("{$dir}/{$i}");
            @unlink("{$dir}/{$i}");
        }
        rmdir($dir);

        return $results;
    }
}
