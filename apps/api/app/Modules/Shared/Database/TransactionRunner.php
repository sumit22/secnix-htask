<?php

declare(strict_types=1);

namespace App\Modules\Shared\Database;

/** Lets one service span several modules in a single DB transaction without seeing their internals. */
interface TransactionRunner
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;
}
