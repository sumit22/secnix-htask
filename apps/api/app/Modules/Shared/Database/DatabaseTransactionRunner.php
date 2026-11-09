<?php

declare(strict_types=1);

namespace App\Modules\Shared\Database;

use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseTransactionRunner implements TransactionRunner
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $work): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $work());
    }
}
