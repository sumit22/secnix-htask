<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Domain\Models\Analyst;

/** @extends UserFactory<Analyst> */
final class AnalystFactory extends UserFactory
{
    protected $model = Analyst::class;
}
