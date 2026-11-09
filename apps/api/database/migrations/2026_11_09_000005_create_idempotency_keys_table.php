<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One row per (requester, key), including deduplicated requests, so a retry always maps to the same lookup.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->foreignUuid('lookup_request_id')->constrained()->cascadeOnDelete();
            $table->boolean('deduplicated');
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->unique(['requested_by', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
