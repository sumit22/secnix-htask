<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Http\Controllers;

use App\Modules\Lookups\Contracts\LookupQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard numbers; lives in Lookups because it combines quota with the waiting queue. */
final readonly class QuotaController
{
    public function __construct(private LookupQuery $query) {}

    public function show(Request $request): JsonResponse
    {
        $request->validate(['provider' => ['nullable', 'string', 'max:50']]);
        $provider = $request->filled('provider') ? $request->string('provider')->toString() : null;

        return new JsonResponse(['data' => $this->query->dashboard($provider)->toArray()]);
    }
}
