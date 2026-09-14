<?php

declare(strict_types=1);

/**
 * Minimal test harness. No framework is allowed, so this is a few dozen lines
 * of assertions and a runner rather than PHPUnit.
 */
class Harness
{
    private static array $tests = [];

    private static int $passed = 0;

    private static array $failures = [];

    private static string $current = '';

    public static function test(string $name, callable $body): void
    {
        self::$tests[] = [$name, $body];
    }

    public static function run(): int
    {
        foreach (self::$tests as [$name, $body]) {
            self::$current = $name;

            try {
                $body();
                self::$passed++;
                echo "  \033[32m✓\033[0m $name\n";
            } catch (\Throwable $e) {
                self::$failures[] = [$name, $e->getMessage()];
                echo "  \033[31m✗\033[0m $name\n      ".$e->getMessage()."\n";
            }
        }

        echo "\n";

        if (self::$failures === []) {
            echo "\033[32mOK\033[0m — ".self::$passed." passed\n";

            return 0;
        }

        echo "\033[31mFAILED\033[0m — ".count(self::$failures)." failed, ".self::$passed." passed\n";

        return 1;
    }

    public static function assertTrue($value, string $message = 'Expected true'): void
    {
        if ($value !== true) {
            throw new \RuntimeException($message.' (got '.var_export($value, true).')');
        }
    }

    public static function assertSame($expected, $actual, string $message = 'Values differ'): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(sprintf('%s: expected %s, got %s', $message, var_export($expected, true), var_export($actual, true)));
        }
    }

    public static function assertCount(int $expected, $actual, string $message = 'Wrong count'): void
    {
        self::assertSame($expected, count($actual), $message);
    }

    /** Asserts the callback throws, optionally checking the exception class. */
    public static function assertThrows(callable $callback, ?string $class = null, ?string $contains = null): \Throwable
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($class !== null && ! ($e instanceof $class)) {
                throw new \RuntimeException('Expected '.$class.' but got '.get_class($e).': '.$e->getMessage());
            }

            if ($contains !== null && ! str_contains($e->getMessage(), $contains)) {
                throw new \RuntimeException('Expected message containing "'.$contains.'", got "'.$e->getMessage().'"');
            }

            return $e;
        }

        throw new \RuntimeException('Expected an exception, none was thrown.');
    }
}
