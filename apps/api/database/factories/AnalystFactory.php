<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Analyst;

/** @extends UserFactory<Analyst> */
final class AnalystFactory extends UserFactory
{
    protected $model = Analyst::class;
}
