<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Identity\Contracts\RequesterDirectory;
use Illuminate\Http\JsonResponse;

final readonly class AnalystController
{
    public function __construct(private RequesterDirectory $directory) {}

    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => array_map(static fn (Requester $r): array => [
            'id' => $r->id,
            'name' => $r->name,
            'team' => ['id' => $r->teamId, 'slug' => $r->teamSlug],
        ], $this->directory->analysts())]);
    }
}
