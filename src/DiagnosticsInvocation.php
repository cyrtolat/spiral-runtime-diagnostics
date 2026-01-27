<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics;

use Cyrtolat\SpiralRuntimeDiagnostics\Contract\InvocationInterface;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;

/**
 * Read-only решение "логировать ли вызов" на основе runfile.
 *
 * Это тонкий адаптер над {@see RunfileRepository}, чтобы interceptor не содержал логику
 * чтения/интерпретации состояния рантайма.
 */
final class DiagnosticsInvocation implements InvocationInterface
{
    /**
     * @param RunfileRepository $storage Источник runfile (чтение/атомарная запись/удаление).
     */
    public function __construct(private readonly RunfileRepository $storage) {}

    /**
     * {@inheritDoc}
     */
    public function shouldLogAt(\DateTimeInterface $at): bool
    {
        $runfile = $this->storage->loadRunfileOrNull();

        if ($runfile === null) {
            return false;
        }

        return $runfile->isActiveAt($at);
    }
}