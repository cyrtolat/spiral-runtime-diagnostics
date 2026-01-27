<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

/**
 * Базовый класс для консольных команд пакета диагностики.
 *
 * Назначение:
 * - убрать дублирование кода в командах;
 * - держать единый стиль вывода;
 * - дать набор маленьких утилит (таблица ключ-значение, форматирование интервала).
 */
abstract class AbstractCommand extends \Spiral\Console\Command
{
    /**
     * Рендерит компактную таблицу ключ-значение.
     *
     * Ключи подсвечиваются цветом comment (как в предыдущей верстке), значения выводятся как есть.
     * В значениях допускаются теги форматирования (например <fg=green>...</>).
     *
     * @param non-empty-string $title Заголовок блока.
     * @param array<array{0: non-empty-string, 1: string}> $rows Строки вида ["ключ", "значение"].
     */
    protected function renderKeyValueTable(string $title, array $rows): void
    {
        $this->writeln("<info>$title</info>");

        // В Spiral\Console\Command метод table() возвращает Table-хелпер.
        // Он не печатает ничего сам по себе — нужно явно вызвать render().
        $table = $this->table(['Параметр', 'Значение']);

        foreach ($rows as [$key, $value]) {
            $table->addRow([
                '<comment>' . $key . '</comment>',
                $value,
            ]);
        }

        $table->render();
    }

    /**
     * Выводит предупреждающее сообщение (alert) в едином формате.
     *
     * Используется для коротких сообщений, когда команда не может выполниться по основному флоу.
     *
     * @param non-empty-string $message Текст сообщения (без тегов форматирования).
     */
    protected function alert(string $message): void
    {
        $this->writeln('<fg=yellow>' . $message . '</>');
    }

    /**
     * Единое предупреждение для режима "бессрочно".
     */
    protected function warnForeverEnabled(): void
    {
        $this->writeln('<fg=cyan>Внимание: диагностика включена бессрочно!</>');
    }

    /**
     * Превращает DateInterval в простую человекочитаемую строку.
     *
     * Встроенного "humanize" форматтера в PHP для DateInterval нет, поэтому используем простой вариант:
     * - показываем дни/часы/минуты/секунды;
     * - нулевые единицы пропускаем;
     * - отрицательный интервал -> "0 сек".
     *
     * Чтобы вывод был компактным — берём максимум две единицы.
     */
    protected function formatDateInterval(\DateInterval $interval): string
    {
        if ($interval->invert === 1) {
            return '0 сек';
        }

        $parts = [];

        $days = $interval->days;
        if ($days === false) {
            $days = $interval->d;
        }

        if ($days > 0) {
            $parts[] = $days . ' д';
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h . ' ч';
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i . ' мин';
        }
        if ($interval->s > 0) {
            $parts[] = $interval->s . ' сек';
        }

        if ($parts === []) {
            return '0 сек';
        }

        return implode(' ', array_slice($parts, 0, 2));
    }
}
