<?php

declare(strict_types=1);

/**
 * Imports landings from the provider.
 *
 * Usage:
 *   php bin/sync.php
 *   php bin/sync.php --sku=drivewaypro,abforge --country=IT --limit=50
 */

require dirname(__DIR__).'/src/autoload.php';

use App\App;

$config = require dirname(__DIR__).'/config/config.php';
$app = new App($config);

$options = getopt('', ['sku::', 'country::', 'limit::']);
$filters = array_filter([
    'sku' => $options['sku'] ?? null,
    'country' => $options['country'] ?? null,
]);

try {
    $summary = $app->sync()->sync($filters, isset($options['limit']) ? (int) $options['limit'] : null);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Sync failed: '.$e->getMessage()."\n");
    exit(1);
}

printf(
    "received %d · created %d · updated %d · unchanged %d · failed %d\n",
    $summary['received'], $summary['created'], $summary['updated'], $summary['unchanged'], $summary['failed']
);
