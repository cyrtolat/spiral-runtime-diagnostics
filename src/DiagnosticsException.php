<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics;

/**
 * Базовое исключение пакета диагностики.
 *
 * Назначение:
 * - единый тип для всех ошибок пакета (их можно ловить как DiagnosticsException);
 * - единый стиль сообщений;
 * - фабричные методы, которые сразу выбрасывают исключение.
 */
final class DiagnosticsException extends \RuntimeException
{
    /**
     * Не удалось сериализовать payload runfile в JSON.
     */
    public static function throwRunfileSerializeException(): never
    {
        throw new self('Не удалось сериализовать runfile диагностики в JSON.');
    }

    /**
     * Не удалось записать временный файл runfile.
     *
     * @param non-empty-string $tmpPath
     */
    public static function throwRunfileTempWriteException(string $tmpPath): never
    {
        throw new self('Не удалось записать временный runfile диагностики: ' . $tmpPath);
    }

    /**
     * Не удалось атомарно заменить runfile (rename()).
     *
     * @param non-empty-string $path
     */
    public static function throwRunfileReplaceException(string $path): never
    {
        throw new self('Не удалось заменить runfile диагностики: ' . $path);
    }

    /**
     * Значение длительности имеет неверный формат.
     */
    public static function throwInvalidDurationFormatException(string $duration): never
    {
        throw new self(
            'Неверный формат длительности: ' . $duration . '. Ожидается например 5m, 1h, 30s, 2d или forever.'
        );
    }

    /**
     * Значение длительности имеет некорректное числовое значение.
     */
    public static function throwInvalidDurationValueException(string $duration): never
    {
        throw new self('Неверное значение длительности: ' . $duration . ' (должно быть > 0).');
    }

    /**
     * Runfile содержит логически некорректный период времени: started_at позже, чем until.
     */
    public static function throwRunfileInvalidPeriodException(
        \DateTimeInterface $startedAt,
        \DateTimeInterface $until
    ): never {
        throw new self(
            'Невалидный runfile диагностики: started_at ('
            . $startedAt->format(\DateTimeInterface::ATOM)
            . ') позже, чем until ('
            . $until->format(\DateTimeInterface::ATOM)
            . ').'
        );
    }

    /**
     * Не задан путь до runfile (diagnostics.runfile_path).
     */
    public static function throwRunfilePathNotConfigured(): never
    {
        throw new self('Не задан путь до runfile диагностики (diagnostics.runfile_path).');
    }

    /**
     * Не удалось построить/применить DateInterval из рассчитанного spec.
     *
     * Используется как защита от неожиданных ошибок DateInterval/DateTime.
     */
    public static function throwDurationIntervalBuildException(
        string $duration,
        string $spec,
        \Throwable $previous
    ): never {
        throw new self(
            'Не удалось применить длительность: ' . $duration . ' (spec=' . $spec . ').',
            0,
            $previous,
        );
    }
}
