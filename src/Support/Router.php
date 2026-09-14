<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal method + pattern router. Patterns use {name} placeholders which are
 * passed to the handler as an associative array.
 */
class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = ['method' => $method, 'regex' => '#^'.$regex.'$#', 'handler' => $handler];
    }

    /**
     * @return array{0:callable,1:array}|null  handler and parameters, or null
     */
    public function match(string $method, string $path): ?array
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (! preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (! is_int($key)) {
                    $params[$key] = $value;
                }
            }

            return [$route['handler'], $params];
        }

        if ($pathMatched) {
            throw new HttpException('Method not allowed.', 405);
        }

        return null;
    }
}
