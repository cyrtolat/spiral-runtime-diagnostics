<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of is_file.
 */
final class IsFileMock
{
    /** @var array<string, bool> */
    private static array $responses = [];

    private static ?bool $defaultResponse = null;

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
    }

    public static function when(string $path, bool $response): void
    {
        self::$responses[$path] = $response;
    }

    /**
     * If set, value will be returned for any path not configured via when().
     * If null, the real global \is_file() will be used.
     */
    public static function setDefault(?bool $response): void
    {
        self::$defaultResponse = $response;
    }

    public static function handle(string $path): bool
    {
        if (\array_key_exists($path, self::$responses)) {
            return self::$responses[$path];
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \is_file($path);
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\IsFileMock;

function is_file(string $filename): bool
{
    return IsFileMock::handle($filename);
}
