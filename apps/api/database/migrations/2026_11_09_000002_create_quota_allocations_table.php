<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Units available per provider × scope × period. team_id NULL = org-level pool.
 * remaining = quota_limit - reserved - consumed; the CHECK makes over-allocation impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedInteger('quota_limit');
            $table->integer('reserved')->default(0);
            $table->integer('consumed')->default(0);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE quota_allocations
                ADD CONSTRAINT quota_allocations_counters_non_negative CHECK (reserved >= 0 AND consumed >= 0),
                ADD CONSTRAINT quota_allocations_within_limit CHECK (reserved + consumed <= quota_limit),
                ADD CONSTRAINT quota_allocations_period_valid CHECK (period_end > period_start),
                ADD CONSTRAINT quota_allocations_scope_period_unique
                    UNIQUE NULLS NOT DISTINCT (provider_id, team_id, period_start)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_allocations');
    }
};
