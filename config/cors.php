<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ? array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS'))))) : ['*'],
    'allowed_origins_patterns' => ['#^http://localhost:\d+$#', '#^http://127\.0\.0\.1:\d+$#'],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Device-ID', 'X-Request-ID', 'Idempotency-Key'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 600,
    'supports_credentials' => false,
];
