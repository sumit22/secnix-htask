<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Contracts\Enums\UserRole;
use App\Modules\Identity\Database\Factories\AnalystFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** A human analyst: a User row with role = analyst (single-table inheritance). */
#[UseFactory(AnalystFactory::class)]
final class Analyst extends User
{
    /** @use HasFactory<AnalystFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        self::addGlobalScope('role', static function (Builder $query): void {
            $query->where('role', UserRole::Analyst);
        });

        self::saving(static function (self $analyst): void {
            $analyst->role = UserRole::Analyst;
        });
    }
}
