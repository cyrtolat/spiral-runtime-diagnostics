<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;

/**
 * Команда вывода текущего статуса диагностики.
 *
 * Читает runfile и выводит:
 * - путь до файла;
 * - включено/выключено/истекло;
 * - время отключения и оставшееся время (если TTL задан).
 */
final class StatusCommand extends AbstractCommand
{
    protected const NAME = 'diag:status';
    protected const DESCRIPTION = 'Показать текущий статус диагностики.';

    public function perform(RunfileRepository $runfileRepository): int
    {
        $now = new \DateTimeImmutable();
        $runfile = $runfileRepository->loadRunfileOrNull();

        if ($runfile === null) {
            $this->alert('Нельзя посмотреть статус - процесс не активен или прерван');
            return self::FAILURE;
        }

        // актуальное состояние (если файл жив, но время истекло)
        $stateText = $runfile->isExpiredAt($now)
            ? '<fg=yellow>завершено</>'
            : '<fg=cyan>активно</>';

        // время, когда процесс был запущен
        $startedText = $runfile->startedAt->format('Y-m-d H:i:s');

        // время, когда диагностика должна была завершиться
        $untilText = is_null($runfile->until) ? '—'
            : $runfile->until->format('Y-m-d H:i:s');

        // сколько времени оставалось до завершения процесса
        $leftText = is_null($runfile->until) ? '—'
            : $this->formatDateInterval($now->diff($runfile->until));

        $this->renderKeyValueTable('Диагностика: текущее состояние', [
            ['Актуальное состояние', $stateText],
            ['Файл-переключатель (runfile)', $runfileRepository->pathToFile],
            ['Время включения', $startedText],
            ['Запланированное время отключения', $untilText],
            ['Осталось времени', $leftText],
        ]);

        if ($runfile->isActiveAt($now) and $runfile->isForever()) {
            $this->warnForeverEnabled();
        }

        return self::SUCCESS;
    }

}
