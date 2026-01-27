<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Support;

/**
 * Фабрика payload'а (context) для logger по одному конкретному перехваченному вызову.
 */
final class InvocationEventFactory
{
    /**
     * Здесь же собираем технические метрики (память) и выполняем форматирование,
     * чтобы вызывающий код (interceptor) оставался максимально простым.
     *
     * @param non-empty-string $action
     *
     * @return array{
     *   action: non-empty-string,
     *   duration_ms: int,
     *   ok: bool,
     *   memory_usage_bytes: int,
     *   memory_peak_bytes: int,
     *   memory_usage: string,
     *   memory_peak: string
     * }
     */
    public static function make(string $action, int $durationMs, bool $ok): array
    {
        return [
            'action' => $action,
            'duration_ms' => $durationMs,
            'ok' => $ok,
            'memory_usage_bytes' => $usageBytes = memory_get_usage(true),
            'memory_peak_bytes' => $peakBytes = memory_get_peak_usage(true),
            'memory_usage' => self::formatBytes($usageBytes),
            'memory_peak' => self::formatBytes($peakBytes),
        ];
    }

    /**
     * Форматирует объём памяти в человекочитаемый вид (B/KB/MB/GB/TB).
     */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $i = 0;

        while (($value >= 1024) and ($i < (count($units) - 1))) {
            $value /= 1024;
            $i++;
        }

        if ($i === 0) {
            return (int)$value . ' ' . $units[$i];
        }

        $formatted = number_format($value, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' ' . $units[$i];
    }
}
