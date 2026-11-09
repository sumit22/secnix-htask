<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Identity\Contracts\RequesterDirectory;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** No auth yet: the caller is identified by X-Analyst-Id and exposed as a Requester request attribute. */
final readonly class ResolveRequester
{
    public const string HEADER = 'X-Analyst-Id';

    public function __construct(private RequesterDirectory $directory) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $requester = $this->directory->find((string) $request->header(self::HEADER, ''));

        if ($requester === null) {
            throw new DomainError(ApiErrorCode::AnalystNotFound, 'Send a valid analyst id in the '.self::HEADER.' header.');
        }

        $request->attributes->set(Requester::class, $requester);

        return $next($request);
    }
}
