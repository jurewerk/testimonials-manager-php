<?php

declare(strict_types=1);

/**
 * Creates an administrator without the demo password.
 *
 * Usage: php bin/create-admin.php "Name" name@example.com
 */

require dirname(__DIR__).'/src/autoload.php';

use App\App;

$config = require dirname(__DIR__).'/config/config.php';
$app = new App($config);

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;

if ($name === null || $email === null) {
    fwrite(STDERR, "Usage: php bin/create-admin.php \"Name\" name@example.com\n");
    exit(1);
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "That email address is not valid.\n");
    exit(1);
}

echo 'Password (at least 12 characters): ';
system('stty -echo 2>/dev/null');
$password = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";

if (strlen($password) < 12) {
    fwrite(STDERR, "The password must be at least 12 characters.\n");
    exit(1);
}

$app->users()->create($name, $email, $password);
echo "Administrator created.\n";
