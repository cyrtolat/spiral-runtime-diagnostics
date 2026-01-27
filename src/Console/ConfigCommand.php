<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsConfiguration;
use Cyrtolat\SpiralRuntimeDiagnostics\Support\ConsoleDurationParser;

/**
 * Команда вывода дефолтной конфигурации диагностики.
 *
 * Полезно для:
 * - быстрой проверки, какие значения реально подхватились из env/config;
 * - понимания, где лежит runfile и какой канал логирования используется.
 */
final class ConfigCommand extends AbstractCommand
{
    protected const NAME = 'diag:config';
    protected const DESCRIPTION = 'Показать дефолтные настройки диагностики.';

    public function perform(DiagnosticsConfiguration $defaults): int
    {
        $this->renderKeyValueTable('Диагностика: конфигурация по умолчанию', [
            ['Длительность включения по умолчанию', $defaults->getDuration()],
            ['Путь до файла-переключателя (runfile)', $defaults->getRunfilePath()],
            ['Канал записи в лог', $defaults->getLogChannel()],
            ['Ключ callable в контексте вызова', $defaults->getCallableAttribute()],
        ]);

        if (!ConsoleDurationParser::parseUntil($defaults->getDuration(), new \DateTimeImmutable())) {
            $this->warnForeverEnabled();
        }

        return self::SUCCESS;
    }
}
