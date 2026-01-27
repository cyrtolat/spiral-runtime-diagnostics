<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of file_put_contents.
 */
final class FilePutContentsMock
{
    /**
     * @var array<string, int|false>
     */
    private static array $responses = [];

    private static int|false|null $defaultResponse = null;

    /** @var list<array{path: string, data: string, flags: int}> */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
        self::$calls = [];
    }

    public static function when(string $path, int|false $response): void
    {
        self::$responses[$path] = $response;
    }

    /**
     * If set, value will be returned for any path not configured via when().
     * If null, the real global \file_put_contents() will be used.
     */
    public static function setDefault(int|false|null $response): void
    {
        self::$defaultResponse = $response;
    }

    /** @return list<array{path: string, data: string, flags: int}> */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(string $path, string $data, int $flags): int|false
    {
        self::$calls[] = ['path' => $path, 'data' => $data, 'flags' => $flags];

        if (\array_key_exists($path, self::$responses)) {
            return self::$responses[$path];
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \file_put_contents($path, $data, $flags);
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\FilePutContentsMock;

function file_put_contents(
    string $filename,
    mixed $data,
    int $flags = 0,
    mixed $context = null,
): int|false {
    // RunfileStorage uses file_put_contents() with a string and no flags.
    // For simplicity we only support string data here.
    $data = (string) $data;

    return FilePutContentsMock::handle($filename, $data, $flags);
}
