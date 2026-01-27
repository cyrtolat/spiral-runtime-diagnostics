<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of rename.
 */
final class RenameMock
{
    /** @var array<string, bool> */
    private static array $responses = [];

    private static ?bool $defaultResponse = null;

    /** @var list<array{from: string, to: string}> */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
        self::$calls = [];
    }

    public static function when(string $from, string $to, bool $response): void
    {
        self::$responses[self::key($from, $to)] = $response;
    }

    /**
     * If set, value will be returned for any pair not configured via when().
     * If null, the real global \rename() will be used.
     */
    public static function setDefault(?bool $response): void
    {
        self::$defaultResponse = $response;
    }

    /** @return list<array{from: string, to: string}> */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(string $from, string $to): bool
    {
        self::$calls[] = ['from' => $from, 'to' => $to];

        $key = self::key($from, $to);
        if (\array_key_exists($key, self::$responses)) {
            return self::$responses[$key];
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \rename($from, $to);
    }

    private static function key(string $from, string $to): string
    {
        return $from . "\0" . $to;
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\RenameMock;

function rename(string $from, string $to, mixed $context = null): bool
{
    return RenameMock::handle($from, $to);
}
