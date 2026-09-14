<?php

declare(strict_types=1);

namespace App\Support;

class Request
{
    public string $method;

    public string $path;

    /** URL prefix the application is served under, "" or "/sub/path". */
    public string $basePath;

    /** Absolute origin + prefix, "https://host/sub/path", for URLs that leave the site. */
    public string $baseUrl;

    private array $query;

    private array $body;

    private array $files;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->basePath = self::detectBasePath();
        $this->baseUrl = self::detectBaseUrl($this->basePath);

        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);

        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $this->path = '/'.trim($uri, '/');
        $this->query = $_GET;
        $this->files = $_FILES;
        $this->body = $this->readBody();
    }

    /**
     * Resolves the URL prefix so the app works at a document root and inside an
     * XAMPP/WAMP subdirectory alike.
     */
    private static function detectBasePath(): string
    {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($dir === '' || $dir === '.') {
            return '';
        }

        if (str_starts_with($uri, $dir)) {
            return $dir;
        }

        // Reached through a rewrite that hides the public/ segment.
        if (str_ends_with($dir, '/public')) {
            $parent = substr($dir, 0, -strlen('/public'));

            return $parent !== '' && str_starts_with($uri, $parent) ? $parent : '';
        }

        return '';
    }

    /**
     * Absolute base URL for links that are consumed off-site — the public API
     * hands image URLs to landing pages on other hosts, where a root-relative
     * path would resolve against the wrong origin.
     *
     * The Host header is attacker-controlled, so it is accepted only if it
     * looks like a host name; set app.url in the configuration to pin it.
     */
    private static function detectBaseUrl(string $basePath): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');

        if (! preg_match('/^[A-Za-z0-9.-]+(:\\d{1,5})?$/', $host)) {
            return $basePath;
        }

        $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        if ($forwarded === 'https' || $forwarded === 'http') {
            $scheme = $forwarded;
        } else {
            $https = (string) ($_SERVER['HTTPS'] ?? '');
            $scheme = $https !== '' && strtolower($https) !== 'off' ? 'https' : 'http';
        }

        return $scheme.'://'.$host.$basePath;
    }

    private function readBody(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body);
    }

    public function files(string $key): array
    {
        if (! isset($this->files[$key])) {
            return [];
        }

        $entry = $this->files[$key];

        // Normalise PHP's awkward multi-file shape into a list of uploads.
        if (! is_array($entry['name'])) {
            return [$entry];
        }

        $out = [];

        foreach (array_keys($entry['name']) as $i) {
            $out[] = [
                'name' => $entry['name'][$i],
                'type' => $entry['type'][$i],
                'tmp_name' => $entry['tmp_name'][$i],
                'error' => $entry['error'][$i],
                'size' => $entry['size'][$i],
            ];
        }

        return $out;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_'.str_replace('-', '_', strtoupper($name));

        return $_SERVER[$key] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }
}
