<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Models;

use App\Modules\Providers\Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A third-party threat-intelligence provider with its own quota.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 */
#[Fillable(['slug', 'name'])]
#[UseFactory(ProviderFactory::class)]
final class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory, HasUuids;
}
