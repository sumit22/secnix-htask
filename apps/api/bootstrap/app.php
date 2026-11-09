<?php

declare(strict_types=1);

use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Indicators\InvalidIndicator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Uniform error envelope (docs/api.md): { error: { code, message, fields? } }.
 *
 * @param  array<string, list<string>>  $fields
 * @param  array<string, string>  $headers
 */
$error = static function (ApiErrorCode $code, string $message, array $fields = [], array $headers = [], ?int $status = null): JsonResponse {
    $body = ['code' => $code->value, 'message' => $message];
    if ($fields !== []) {
        $body['fields'] = $fields;
    }

    return new JsonResponse(['error' => $body], $status ?? $code->httpStatus(), $headers);
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registers the default middleware groups (incl. "api"); nothing custom yet.
    ->withMiddleware(static function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([DomainError::class, InvalidIndicator::class]);
        $exceptions->render(fn (DomainError $e) => $error($e->errorCode, $e->getMessage(), [], $e->headers));
        $exceptions->render(fn (InvalidIndicator $e) => $error(ApiErrorCode::ValidationFailed, $e->getMessage(), ['indicator' => [$e->getMessage()]]));
        $exceptions->render(fn (ValidationException $e) => $error(ApiErrorCode::ValidationFailed, 'The request is invalid.', $e->errors()));
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? $error(ApiErrorCode::NotFound, 'Not found.')
            : null);
        // Any other framework HTTP error (405, 429, …) keeps the envelope and its own status.
        $exceptions->render(fn (HttpExceptionInterface $e, Request $request) => $request->is('api/*')
            ? $error(ApiErrorCode::HttpError, $e->getMessage() !== '' ? $e->getMessage() : 'Request rejected.', [], [], $e->getStatusCode())
            : null);
        // Unexpected failures: envelope without internals unless debugging.
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is('api/*') && ! config('app.debug')
            ? $error(ApiErrorCode::InternalError, 'Something went wrong.')
            : null);
    })->create();
