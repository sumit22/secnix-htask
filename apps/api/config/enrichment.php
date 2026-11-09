<?php

declare(strict_types=1);

// Enrichment coordinator settings (docs/hld.md §5). Exposed read-only to the web app via the API.
return [

    // How long a reserved unit is held for its requester before it returns to the pool.
    'lease_ttl_seconds' => (int) env('ENRICHMENT_LEASE_TTL_SECONDS', 300),

    // Org-level units per provider per UTC day, used when a day's allocation is first created.
    'default_daily_quota' => (int) env('ENRICHMENT_DEFAULT_DAILY_QUOTA', 10),

    // Provider used when a request does not name one (only the mock exists today).
    'default_provider' => env('ENRICHMENT_DEFAULT_PROVIDER', 'mock-intel'),

];
