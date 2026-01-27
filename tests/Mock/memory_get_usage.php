<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock;

/**
 * Helper for namespace-level function mock of memory_get_usage.
 *
 * Important: This mock only works if tests include this file BEFORE the first time
 * Cyrtolat\SpiralRuntimeDiagnostics\Logging\EventFactory is loaded/used.
 */
final class MemoryGetUsageMock
{
    /**
     * @var list<array{real_usage: bool}>
     */
    private static array $calls = [];

    private static ?int $defaultResponse = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$defaultResponse = null;
    }

    /**
     * If set, this value will be returned for any call.
     * If null, the real global \memory_get_usage() will be used.
     */
    public static function setDefault(?int $bytes): void
    {
        self::$defaultResponse = $bytes;
    }

    /**
     * @return list<array{real_usage: bool}>
     */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function handle(bool $realUsage): int
    {
        self::$calls[] = ['real_usage' => $realUsage];

        if (self::$defaultResponse !== null) {
            return self::$defaultResponse;
        }

        return \memory_get_usage($realUsage);
    }
}

// -----------------------------------------------------------------------------
// Namespace-level function override for code under test.
// -----------------------------------------------------------------------------

namespace Cyrtolat\SpiralRuntimeDiagnostics\Logging;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetUsageMock;

/**
 * Mockable version of memory_get_usage.
 */
function memory_get_usage(bool $real_usage = false): int
{
    return MemoryGetUsageMock::handle($real_usage);
}

// -----------------------------------------------------------------------------
// Same override for Process namespace.
// -----------------------------------------------------------------------------

namespace Cyrtolat\SpiralRuntimeDiagnostics\Process;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetUsageMock;

/**
 * Mockable version of memory_get_usage.
 */
function memory_get_usage(bool $real_usage = false): int
{
    return MemoryGetUsageMock::handle($real_usage);
}

// -----------------------------------------------------------------------------
// Same override for Helpers namespace (InvocationEventFactory::make).
// -----------------------------------------------------------------------------

namespace Cyrtolat\SpiralRuntimeDiagnostics\Support;

use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetUsageMock;

/**
 * Mockable version of memory_get_usage.
 */
function memory_get_usage(bool $real_usage = false): int
{
    return MemoryGetUsageMock::handle($real_usage);
}
