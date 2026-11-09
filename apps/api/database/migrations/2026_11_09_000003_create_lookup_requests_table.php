<?php

declare(strict_types=1);

use App\Enums\IndicatorType;
use App\Enums\LookupStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A lookup request and its lease (a lease is a state of the request, see docs/hld.md §4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('team_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('quota_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->string('indicator_type', 10);
            $table->string('indicator_value', 255);
            $table->string('status', 10);
            $table->timestampTz('leased_at')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->jsonb('result')->nullable();
            $table->timestampsTz();

            $table->unique(['requested_by', 'idempotency_key']);
        });

        $statuses = $this->sqlList(array_column(LookupStatus::cases(), 'value'));
        $types = $this->sqlList(array_column(IndicatorType::cases(), 'value'));
        $active = $this->sqlList(array_map(static fn (LookupStatus $s): string => $s->value, LookupStatus::active()));

        DB::statement("ALTER TABLE lookup_requests
            ADD CONSTRAINT lookup_requests_status_check CHECK (status IN ({$statuses})),
            ADD CONSTRAINT lookup_requests_indicator_type_check CHECK (indicator_type IN ({$types})),
            ADD CONSTRAINT lookup_requests_leased_has_expiry CHECK (status <> 'leased' OR lease_expires_at IS NOT NULL)");

        // At most one active (queued or leased) lookup per indicator and provider.
        DB::statement("CREATE UNIQUE INDEX lookup_requests_one_active_per_indicator
            ON lookup_requests (provider_id, indicator_type, indicator_value)
            WHERE status IN ({$active})");

        // Expiry sweep scans only live leases.
        DB::statement("CREATE INDEX lookup_requests_live_lease_expiry
            ON lookup_requests (lease_expires_at) WHERE status = 'leased'");
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_requests');
    }

    /** @param list<string> $values */
    private function sqlList(array $values): string
    {
        return "'".implode("','", $values)."'";
    }
};
