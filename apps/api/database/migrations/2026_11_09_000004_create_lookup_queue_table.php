<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO waiting list. The bigint identity is the ordering key: a sequence is strictly monotonic
 * across sessions (UUIDv7 is not). It never leaves the database; the API exposes a computed position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_queue', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('lookup_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestampTz('enqueued_at')->default(DB::raw('now()'));

            $table->index(['provider_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_queue');
    }
};
