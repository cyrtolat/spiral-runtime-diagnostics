<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of json_decode.
 *
 * Notes:
 * - json_decode returns mixed.
 * - For simplicity we do not emulate json_last_error() state.
 */
final class JsonDecodeMock
{
    /**
     * @var array<string, mixed>
     */
    private static array $responses = [];

    private static mixed $defaultResponse = null;

    /** @var list<array{json: string, assoc: bool|null, depth: int, flags: int}> */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$defaultResponse = null;
        self::$calls = [];
    }

    /**
     * Configure return value for exact JSON string.
     */
    public static function when(string $json, mixed $response): void
    {
        self::$responses[$json] = $response;
    }

    /**
     * If set, this value will be returned when JSON is not configured via when().
     * If null, the real global \json_decode() will be used.
     */
    public static function setDefault(mixed $response): void
    {
        self::$defaultResponse = $response;
    }

    /** @return list<array{json: string, assoc: bool|null, depth: int, flags: int}> */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(string $json, bool|null $assoc, int $depth, int $flags): mixed
    {
        self::$calls[] = ['json' => $json, 'assoc' => $assoc, 'depth' => $depth, 'flags' => $flags];

        if (\array_key_exists($json, self::$responses)) {
            return self::$responses[$json];
        }

        // If defaultResponse is anything other than null, return it.
        // If it is null, delegate to the real function to keep default behavior.
        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \json_decode($json, $assoc, $depth, $flags);
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\JsonDecodeMock;

function json_decode(string $json, bool|null $associative = null, int $depth = 512, int $flags = 0): mixed
{
    return JsonDecodeMock::handle($json, $associative, $depth, $flags);
}
