<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

/** Validates API responses against the shared JSON Schemas (contracts/schemas, synced to tests/Contracts). */
trait AssertsContract
{
    private const string CONTRACT_BASE = 'https://secnix.local/contracts/';

    private static ?Validator $contractValidator = null;

    /**
     * Checks a /lookups response: `data` against LookupView on success, the error envelope otherwise.
     *
     * @param  TestResponse<Response>  $response
     * @return TestResponse<Response>
     */
    protected function assertLookupContract(TestResponse $response): TestResponse
    {
        $response->isSuccessful()
            ? $this->assertMatchesContract('lookup-view.schema.json', $response->json('data'))
            : $this->assertMatchesContract('api-error.schema.json', $response->json());

        return $response;
    }

    protected function assertMatchesContract(string $schema, mixed $data): void
    {
        $result = self::contractValidator()->validate(
            json_decode((string) json_encode($data, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR),
            self::CONTRACT_BASE.$schema,
        );

        $error = $result->error();
        self::assertNull($error, $schema.' violated: '.json_encode(
            $error === null ? [] : (new ErrorFormatter)->format($error),
            JSON_UNESCAPED_SLASHES,
        ));
    }

    private static function contractValidator(): Validator
    {
        if (self::$contractValidator === null) {
            $validator = new Validator;
            $files = glob(dirname(__DIR__).'/Contracts/schemas/*.schema.json');
            self::assertNotEmpty($files, 'Run `node scripts/contracts.mjs` to sync the contract schemas.');
            foreach ($files === false ? [] : $files as $file) {
                $validator->resolver()?->registerFile(self::CONTRACT_BASE.basename($file), $file);
            }
            self::$contractValidator = $validator;
        }

        return self::$contractValidator;
    }
}
