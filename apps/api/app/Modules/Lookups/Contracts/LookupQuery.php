<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;

/** Reads apply overdue expiries first, so clients never see a stale lease. */
interface LookupQuery
{
    public function find(string $lookupId): LookupView;

    /** @return list<LookupView> newest first */
    public function recent(?string $requestedBy, ?LookupStatus $status, int $limit = 50): array;

    public function dashboard(?string $provider): QuotaDashboard;
}
