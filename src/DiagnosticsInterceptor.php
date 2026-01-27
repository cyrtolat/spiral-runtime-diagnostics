<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics;

use Cyrtolat\SpiralRuntimeDiagnostics\Contract\InvocationInterface;
use Cyrtolat\SpiralRuntimeDiagnostics\Support\CallableNameFormatter;
use Cyrtolat\SpiralRuntimeDiagnostics\Support\InvocationEventFactory;
use Psr\Log\LoggerInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

/**
 * Интерцептор для быстрой runtime-диагностики.
 *
 * Назначение:
 * - когда диагностика выключена — interceptor делает минимальную работу и быстро делегирует вызов;
 * - когда диагностика включена — логирует каждый вызов:
 *   - успешный вызов -> info
 *   - исключение -> warning + флаг exception=true
 *
 * Переключение режима выполняется в рантайме через CLI (diag:start/diag:stop),
 * без деплоя и без перезапуска RoadRunner воркеров.
 */
final class DiagnosticsInterceptor implements InterceptorInterface
{
    private const LOG_MESSAGE = 'diagnostics.action';

    /**
     * @param non-empty-string $callableAttribute Ключ, по которому в CallContext attributes хранится исходный
     *     callable. Обычно берётся из конфигурации `diagnostics.callable_attribute`.
     * @param LoggerInterface $logger PSR-логгер, в который пишем диагностические события (channel выбирается при
     *      wiring).
     * @param InvocationInterface $invocation решает, активна ли диагностика в момент времени.
     */
    public function __construct(
        private readonly string $callableAttribute,
        private readonly LoggerInterface $logger,
        private readonly InvocationInterface $invocation,
    ) {}

    /**
     * Основная точка перехвата.
     *
     * Алгоритм:
     * 1) Проверяем, активна ли диагностика на текущий момент.
     * 2) Если нет — просто делегируем вызов дальше.
     * 3) Если да — замеряем время выполнения и логируем событие.
     *
     * @throws \Throwable Пробрасывает исходное исключение, чтобы не менять поведение приложения.
     */
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        if (!$this->invocation->shouldLogAt(new \DateTimeImmutable())) {
            return $handler->handle($context);
        }

        $actionStartAt = hrtime(true); // старт в наносекундах
        $actionCallName = $this->resolveActionName($context);

        try {
            $result = $handler->handle($context);
            $durationMs = $this->elapsedMsSince($actionStartAt);

            $event = InvocationEventFactory::make($actionCallName, $durationMs, true);
            $this->logger->info(self::LOG_MESSAGE, $event);

            return $result;
        } catch (\Throwable $e) {
            $durationMs = $this->elapsedMsSince($actionStartAt);

            $event = InvocationEventFactory::make($actionCallName, $durationMs, false);
            $this->logger->warning(self::LOG_MESSAGE, $event);

            throw $e;
        }
    }

    /**
     * Возвращает прошедшее время в миллисекундах с момента $startedAtNs.
     *
     * @param int $startTimeNs Время старта в наносекундах (hrtime(true)).
     *
     * @return int Прошедшее время в миллисекундах.
     */
    private function elapsedMsSince(int $startTimeNs): int
    {
        return (int)((hrtime(true) - $startTimeNs) / 1_000_000);
    }

    /**
     * Пытается получить человекочитаемое имя выполняемого action.
     *
     * Алгоритм:
     * - сперва используем (string)$context->getTarget();
     * - если пусто (например, closure) — пытаемся достать исходный callable из attributes
     *   по ключу $this->callableAttribute;
     * - строим best-effort строковое имя через CallableNameFormatter::parseCallable();
     * - если не удалось — возвращаем "unknown".
     *
     * @throws \ReflectionException
     * @return non-empty-string
     */
    private function resolveActionName(CallContextInterface $context): string
    {
        $fromTarget = (string)$context->getTarget();
        if ($fromTarget !== '') {
            return $fromTarget;
        }

        $callable = $context->getAttribute($this->callableAttribute);
        $resolved = CallableNameFormatter::parseCallable($callable);

        return ($resolved !== '') ? $resolved : 'unknown';
    }
}
