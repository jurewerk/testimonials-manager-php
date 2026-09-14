<?php

/**
 * Router for PHP's built-in development server:
 *
 *   php -S 127.0.0.1:8000 -t public bin/router.php
 *
 * Apache does this with public/.htaccess; the built-in server needs to be told
 * to serve real files itself and hand everything else to the front controller.
 * Not used in production.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__.'/../public'.$path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__.'/../public/index.php';
