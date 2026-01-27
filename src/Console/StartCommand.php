<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsConfiguration;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;
use Cyrtolat\SpiralRuntimeDiagnostics\Support\ConsoleDurationParser;

/**
 * Команда включения режима диагностического логирования.
 *
 * Команда создаёт runfile на диске, который затем читается воркерами (через interceptor) на каждом вызове.
 *
 * Использование:
 * - diag:start
 * - diag:start --duration=5m
 * - diag:start --duration=forever
 */
final class StartCommand extends AbstractCommand
{
    protected const NAME = 'diag:start';
    protected const DESCRIPTION = 'Запустить режим диагностики.';

    protected const SIGNATURE = 'diag:start {--duration= : Длительность включения (например 5m, 1h, 2d, forever).}';

    public function perform(RunfileRepository $runfileRepository, DiagnosticsConfiguration $config): int
    {
        $now = new \DateTimeImmutable();
        $runfile = $runfileRepository->loadRunfileOrNull();

        // если процесс уже запущен перезапустить его нельзя
        if (!is_null($runfile) and $runfile->isActiveAt($now)) {
            $this->alert('Нельзя запустить процесс - он уже запущен');
            return self::FAILURE;
        }

        $duration = $this->option('duration') ?? $config->getDuration();

        $runfile = new Runfile(
            isEnabled: true,
            startedAt: $now,
            until: ConsoleDurationParser::parseUntil($duration, new \DateTimeImmutable())
        );

        $runfileRepository->saveRunfileOrFail($runfile);

        $this->renderKeyValueTable('Диагностика: запуск процесса', [
            ['Длительность включения', "<fg=cyan>$duration</>"],
            ['Время отключения', $runfile->until?->format(\DateTimeInterface::ATOM) ?? '—'],
        ]);

        if ($runfile->isForever()) {
            $this->warnForeverEnabled();
        }

        return self::SUCCESS;
    }
}
