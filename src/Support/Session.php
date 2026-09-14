<?php

declare(strict_types=1);

namespace App\Support;

class Session
{
    public function __construct(array $config)
    {
        // On the command line (seeding, sync, tests) there is no session to
        // start; $_SESSION is just an array so the rest of the code is unaware.
        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];

            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($config['name']);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
        ]);

        session_start();

        // Drop sessions that have been idle longer than the configured lifetime.
        $now = time();

        if (isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > $config['lifetime']) {
            $this->destroy();
            session_start();
        }

        $_SESSION['last_seen'] = $now;
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
