<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\RequesterDirectoryService;
use App\Modules\Identity\Contracts\RequesterDirectory;
use App\Modules\Identity\Domain\Repositories\UserRepository;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        UserRepository::class => EloquentUserRepository::class,
        RequesterDirectory::class => RequesterDirectoryService::class,
    ];
}
