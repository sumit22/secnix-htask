<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Http\Controllers;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Lookups\Contracts\EnrichmentRequest;
use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\LookupQuery;
use App\Modules\Lookups\Contracts\LookupView;
use App\Modules\Lookups\Contracts\RequestEnrichment;
use App\Modules\Lookups\Contracts\RunLookup;
use App\Modules\Lookups\Http\Requests\StoreLookupRequest;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final readonly class LookupController
{
    private const int MAX_KEY_LENGTH = 100;

    public function __construct(
        private RequestEnrichment $enrichment,
        private RunLookup $runner,
        private LookupQuery $query,
    ) {}

    public function store(StoreLookupRequest $request): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            throw new DomainError(ApiErrorCode::IdempotencyKeyMissing, 'Send an Idempotency-Key header (1–100 characters).');
        }

        $outcome = $this->enrichment->request(new EnrichmentRequest(
            self::requester($request), $key, $request->indicator(), $request->indicatorType(), $request->provider(),
        ));

        $headers = $outcome->replayed ? ['Idempotent-Replayed' => 'true'] : [];
        if ($outcome->created) {
            $headers['Location'] = url('/api/v1/lookups/'.$outcome->view->id);
        }

        return self::respond($outcome->view, $outcome->created ? 201 : 200, $headers);
    }

    public function show(string $id): JsonResponse
    {
        return self::respond($this->query->find($id));
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->enum('status', LookupStatus::class);
        $requestedBy = $request->filled('requested_by') ? $request->string('requested_by')->toString() : null;

        return new JsonResponse(['data' => array_map(
            static fn (LookupView $v): array => $v->toArray(),
            $this->query->recent($requestedBy, $status),
        )]);
    }

    public function run(Request $request, string $id): JsonResponse
    {
        return self::respond($this->runner->run($id, self::requester($request)));
    }

    /** @param array<string, string> $headers */
    private static function respond(LookupView $view, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse(['data' => $view->toArray()], $status, $headers);
    }

    private static function requester(Request $request): Requester
    {
        $requester = $request->attributes->get(Requester::class);

        return $requester instanceof Requester ? $requester : throw new LogicException('Requester middleware missing.');
    }
}
