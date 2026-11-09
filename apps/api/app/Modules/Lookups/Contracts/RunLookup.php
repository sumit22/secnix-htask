<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

use App\Modules\Identity\Contracts\Requester;

/** Call the provider for a live lease and consume its unit; re-running a completed lookup returns its result. */
interface RunLookup
{
    public function run(string $lookupId, Requester $requester): LookupView;
}
