<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;

/**
 * Команда выключения режима диагностики.
 *
 * Реализация максимально простая: удаляет runfile.
 * Это безопасная и идемпотентная операция.
 */
final class StopCommand extends AbstractCommand
{
    protected const NAME = 'diag:stop';
    protected const DESCRIPTION = 'Прервать режим диагностического логирования.';

    public function perform(RunfileRepository $runfileRepository): int
    {
        $now = new \DateTimeImmutable();
        $runfile = $runfileRepository->loadRunfileOrNull();

        // если runfile отсутствует или диагностика уже завершилась
        if (($runfile === null) or $runfile->isExpiredAt($now)) {
            $this->alert('Процесс не активен');
            return self::FAILURE;
        }

        $runfileRepository->removeRunfileOrFalse();

        // время, когда диагностика должна была завершиться
        $untilText = is_null($runfile->until) ? '—'
            : $runfile->until->format('Y-m-d H:i:s');

        // сколько времени оставалось до завершения процесса
        $leftText = is_null($runfile->until) ? '—'
            : $this->formatDateInterval($now->diff($runfile->until));

        // сколько прошло с момента включения
        $workedText = $this->formatDateInterval($runfile->startedAt->diff($now));

        $this->renderKeyValueTable('Диагностика: остановка процесса', [
            ['Запланированное время отключения', $untilText],
            ['Оставалось до отключения', $leftText],
            ['Проработало с момента включения', $workedText],
        ]);

        return self::SUCCESS;
    }
}
