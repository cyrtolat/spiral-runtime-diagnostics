<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Contract;

/**
 * Контракт runtime-переключателя диагностики.
 *
 * Нужен, чтобы interceptor мог быстро понять, нужно ли логировать текущий вызов,
 * не зная, где и как хранится состояние (runfile, кеш, внешняя система и т.д.)
 * и не имея возможности управлять состоянием процесса диагностики.
 */
interface InvocationInterface
{
    /**
     * Возвращает true, если диагностика активна в момент времени $at и текущий вызов нужно логировать.
     *
     * @param \DateTimeInterface $at Момент времени, для которого проверяется активность диагностики.
     *
     * @return bool true если диагностика активна и false если логгировать не нужно
     */
    public function shouldLogAt(\DateTimeInterface $at): bool;
}
