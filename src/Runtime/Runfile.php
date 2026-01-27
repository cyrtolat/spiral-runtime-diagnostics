<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;

/**
 * Содержимое runfile (runtime override-переключатель диагностики).
 *
 * Это не конфигурация приложения (config/diagnostics.php), а краткоживущий внешний переключатель,
 * которым управляют через CLI в рантайме, без деплоя и без перезапуска воркеров.
 *
 * Поля runfile:
 * - enabled: включена ли диагностика;
 * - started_at: когда режим был включён;
 * - until: до какого времени включена (или null, если до diag:stop).
 */
final class Runfile
{
    public const STATE_OFF = 'off';
    public const STATE_ACTIVE = 'active';
    public const STATE_EXPIRED = 'expired';

    /**
     * @param bool $isEnabled Флаг включения диагностики.
     * @param \DateTimeInterface $startedAt Момент включения режима диагностики.
     * @param \DateTimeInterface|null $until Время истечения режима или null (до diag:stop).
     *
     * @throws DiagnosticsException Если задан until и startedAt > until.
     */
    public function __construct(
        public readonly bool $isEnabled,
        public readonly \DateTimeInterface $startedAt,
        public readonly ?\DateTimeInterface $until,
    ) {
        if (($this->until !== null) and ($this->startedAt > $this->until)) {
            DiagnosticsException::throwRunfileInvalidPeriodException($this->startedAt, $this->until);
        }
    }

    /**
     * Возвращает состояние диагностики на момент времени $at.
     *
     * Состояния:
     * - off: enabled=false (legacy-формат; обычно off означает отсутствие runfile)
     * - active: enabled=true и (until=null или at < until)
     * - expired: enabled=true и until!=null и at >= until
     *
     * @param \DateTimeInterface $at Момент времени, для которого вычисляется состояние.
     *
     * @return self::STATE_OFF|self::STATE_ACTIVE|self::STATE_EXPIRED
     */
    public function stateAt(\DateTimeInterface $at): string
    {
        if (!$this->isEnabled) {
            return self::STATE_OFF;
        }

        if ($this->until === null) {
            return self::STATE_ACTIVE;
        }

        return ($at < $this->until) ? self::STATE_ACTIVE : self::STATE_EXPIRED;
    }

    /**
     * Проверяет, активен ли режим диагностики в момент времени $at.
     *
     * Правила:
     * - enabled=false => неактивно (независимо от until);
     * - until=null => активно бессрочно (до diag:stop);
     * - until задан => активно, пока at < until.
     *
     * @param \DateTimeInterface $at Момент времени, для которого выполняется проверка.
     */
    public function isActiveAt(\DateTimeInterface $at): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if ($this->until === null) {
            return true;
        }

        return $at < $this->until;
    }

    /**
     * Проверяет, что диагностика истекла на момент времени $at.
     *
     * @param \DateTimeInterface $at Момент времени, для которого выполняется проверка.
     */
    public function isExpiredAt(\DateTimeInterface $at): bool
    {
        return $this->stateAt($at) === self::STATE_EXPIRED;
    }

    /**
     * Возвращает true, если диагностика включена "навсегда" (until отсутствует).
     *
     * @return bool true если включено навсегда
     */
    public function isForever(): bool
    {
        return $this->until === null;
    }
}