<?php

declare(strict_types=1);

/**
 * Configuration is read from config/config.local.php when present, otherwise
 * from environment variables, otherwise from the defaults below. The API key,
 * the provider's endpoint and the database password therefore never need to
 * live in version control.
 */
$local = __DIR__.'/config.local.php';
$overrides = is_file($local) ? require $local : [];

$env = static function (string $key, $default = null) {
    $value = getenv($key);

    return $value === false || $value === '' ? $default : $value;
};

return array_replace_recursive([
    'app' => [
        'name' => 'Testimonials Manager',
        'debug' => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        // Pin the public origin when the app sits behind a proxy or a CDN;
        // empty means "derive it from the request".
        'url' => rtrim((string) $env('APP_URL', ''), '/'),
    ],
    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'testimonials'),
        'user' => $env('DB_USER', 'root'),
        'pass' => $env('DB_PASS', ''),
    ],
    'uploads' => [
        // Files are stored outside the document root and streamed back by PHP.
        'path' => dirname(__DIR__).'/storage/uploads',
        'max_bytes' => 5 * 1024 * 1024,
        'max_pixels' => 24_000_000,
        'max_dimension' => 2000,
        'thumb_width' => 320,
        'thumb_height' => 240,
        'max_per_request' => 10,
    ],
    'landings_api' => [
        // Supplied with the assignment, together with the key. Both belong in
        // config/config.local.php, which is not in version control.
        'url' => $env('LANDINGS_API_URL', ''),
        'key' => $env('LANDINGS_API_KEY', ''),
        'page_size' => 500,
        'timeout' => 20,
    ],
    'session' => [
        'name' => 'testimonials_session',
        'lifetime' => 7200,
    ],
], $overrides);
