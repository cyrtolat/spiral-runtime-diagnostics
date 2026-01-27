<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of unlink.
 */
final class UnlinkMock
{
    /** @var array<string, bool> */
    private static array $responses = [];

    private static ?bool $defaultResponse = null;

    /** @var list<string> */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
        self::$calls = [];
    }

    public static function when(string $path, bool $response): void
    {
        self::$responses[$path] = $response;
    }

    /**
     * If set, value will be returned for any path not configured via when().
     * If null, the real global \unlink() will be used.
     */
    public static function setDefault(?bool $response): void
    {
        self::$defaultResponse = $response;
    }

    /** @return list<string> */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(string $path): bool
    {
        self::$calls[] = $path;

        if (\array_key_exists($path, self::$responses)) {
            return self::$responses[$path];
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \unlink($path);
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\UnlinkMock;

function unlink(string $filename, mixed $context = null): bool
{
    return UnlinkMock::handle($filename);
}
