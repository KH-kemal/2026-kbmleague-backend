<?php

$origins = [
    'https://kbmleague.my.id',
    'https://www.kbmleague.my.id',
];

if (env('APP_ENV', 'production') === 'local') {
    $origins = array_merge($origins, [
        'http://localhost:5173', 'http://127.0.0.1:5173',
        'http://localhost:5174', 'http://127.0.0.1:5174',
    ]);
}

// Explicit opt-in, e.g. a local frontend testing the hosted API.
$extra = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));
foreach ($extra as $origin) {
    $parts = parse_url($origin);
    if ($parts && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        && isset($parts['host']) && !str_contains($parts['host'], '*')
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query'])
        && !isset($parts['fragment']) && empty($parts['path'])) {
        $origins[] = $origin;
    }
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique($origins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 600,
    'supports_credentials' => false,
];
