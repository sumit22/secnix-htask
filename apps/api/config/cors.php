<?php

declare(strict_types=1);

// Browser access from the web app only; origins are an explicit allow-list (no wildcard).
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'Idempotency-Key', 'X-Analyst-Id'],
    'exposed_headers' => ['Location', 'Idempotent-Replayed', 'Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
