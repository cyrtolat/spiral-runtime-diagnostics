<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of json_encode.
 *
 * Notes:
 * - json_encode normally returns string|false.
 * - For simplicity we do not emulate json_last_error() state.
 */
final class JsonEncodeMock
{
    /**
     * @var list<array{matcher: \Closure(mixed,int,int): bool, response: string|false}>
     */
    private static array $rules = [];

    private static string|false|null $defaultResponse = null;

    /** @var list<array{value: mixed, flags: int, depth: int}> */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$rules = [];
        self::$defaultResponse = null;
        self::$calls = [];
    }

    /**
     * Add a matching rule.
     *
     * @param callable(mixed,int,int): bool $matcher Receives ($value, $flags, $depth).
     * @param string|false $response
     */
    public static function when(callable $matcher, string|false $response): void
    {
        self::$rules[] = ['matcher' => $matcher(...), 'response' => $response];
    }

    /**
     * If set, this value will be returned when no rules match.
     * If null, the real global \json_encode() will be used.
     */
    public static function setDefault(string|false|null $response): void
    {
        self::$defaultResponse = $response;
    }

    /** @return list<array{value: mixed, flags: int, depth: int}> */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(mixed $value, int $flags, int $depth): string|false
    {
        self::$calls[] = ['value' => $value, 'flags' => $flags, 'depth' => $depth];

        foreach (self::$rules as $rule) {
            if (($rule['matcher'])($value, $flags, $depth)) {
                return $rule['response'];
            }
        }

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \json_encode($value, $flags, $depth);
    }
}

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\JsonEncodeMock;

function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    return JsonEncodeMock::handle($value, $flags, $depth);
}
