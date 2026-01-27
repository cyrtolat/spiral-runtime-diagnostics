<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Support;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;

/**
 * Парсер длительности включения (TTL).
 *
 * Превращает человекочитаемое значение ("5m", "1h", "2d") в абсолютный момент отключения диагностики.
 * "forever" и пустое/пробельное значение означают бессрочно (до ручного прерывания процесса).
 */
final class ConsoleDurationParser
{
    /**
     * Парсит длительность и возвращает момент времени отключения.
     *
     * Поддерживаемые форматы:
     * - "30s" — секунды
     * - "5m"  — минуты
     * - "1h"  — часы
     * - "2d"  — дни
     * - "forever" или пустое/пробельное значение — бессрочно -> null
     *
     * @param string $duration Значение, введённое пользователем (например, из CLI-опции).
     * @param \DateTimeImmutable $now Момент времени, относительно которого рассчитываем TTL.
     *
     * @throws DiagnosticsException Если значение не удалось распарсить или построить интервал.
     * @return \DateTimeImmutable|null null означает "до выполнения diag:stop".
     */
    public static function parseUntil(string $duration, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $duration = strtolower(trim($duration));

        if (($duration === '') or ($duration === 'forever')) {
            return null;
        }

        if (!preg_match('/^(\d+)([smhd])$/', $duration, $m)) {
            DiagnosticsException::throwInvalidDurationFormatException($duration);
        }

        $n = (int)$m[1];
        $unit = $m[2];

        if ($n <= 0) {
            DiagnosticsException::throwInvalidDurationValueException($duration);
        }

        $spec = match ($unit) {
            's' => 'PT' . $n . 'S',
            'm' => 'PT' . $n . 'M',
            'h' => 'PT' . $n . 'H',
            'd' => 'P' . $n . 'D',
        };

        try {
            return $now->add(new \DateInterval($spec));
        } catch (\Throwable $e) {
            DiagnosticsException::throwDurationIntervalBuildException($duration, $spec, $e);
        }
    }
}