<?php

declare(strict_types=1);

namespace App\Modules\Providers\Contracts;

final readonly class ProviderRef
{
    public function __construct(
        public string $id,
        public string $slug,
    ) {}
}
