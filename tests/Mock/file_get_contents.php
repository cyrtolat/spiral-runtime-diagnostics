<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of file_get_contents.
 *
 * Important: This mock only works if tests include this file BEFORE the first time
 * Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileStorage is loaded/used.
 */
final class FileGetContentsMock
{
    /**
     * @var array<string, string|false>
     */
    private static array $responses = [];

    private static string|false|null $defaultResponse = null;

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
    }

    public static function when(string $path, string|false $response): void
    {
        self::$responses[$path] = $response;
    }

    /**
     * If set, this value will be returned for any path not configured via when().
     * If null, the real global \file_get_contents() will be used.
     */
    public static function setDefault(string|false|null $response): void
    {
        self::$defaultResponse = $response;
    }

    public static function handle(string $path): string|false
    {
        if (\array_key_exists($path, self::$responses)) {
            return self::$responses[$path];
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \file_get_contents($path);
    }
}

// -----------------------------------------------------------------------------
// Namespace-level function override for code under test.
// -----------------------------------------------------------------------------

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\FileGetContentsMock;

/**
 * Mockable version of file_get_contents.
 *
 * RunfileStorage calls `file_get_contents()` without leading backslash, therefore PHP will
 * first try to resolve it inside this namespace.
 */
function file_get_contents(
    string $filename,
    bool $use_include_path = false,
    mixed $context = null,
    int $offset = 0,
    ?int $length = null,
): string|false {
    // Note: we intentionally ignore $use_include_path/$context/$offset/$length for simplicity.
    // Add them to the key/behavior if you ever need more precise matching.
    return FileGetContentsMock::handle($filename);
}
