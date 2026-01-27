<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

require_once __DIR__ . '/Mock/mock_functions_kit.php';

use Cyrtolat\SpiralRuntimeDiagnostics\Contract\InvocationInterface;
use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsInterceptor;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetPeakUsageMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetUsageMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;

#[CoversClass(DiagnosticsInterceptor::class)]
#[TestDox('DiagnosticsInterceptor: минимальные сценарии (delegate / info / warning)')]
final class DiagnosticsInterceptorTest extends TestCase
{
    protected function tearDown(): void
    {
        MemoryGetUsageMock::reset();
        MemoryGetPeakUsageMock::reset();
    }

    // =================================================================================================================
    // intercept()
    // =================================================================================================================

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\DiagnosticsInterceptor::intercept
     */
    #[Test]
    #[TestDox('intercept: если диагностика не активна, просто делегирует handler и не логирует')]
    public function testInterceptDelegatesWhenDiagnosticsIsInactive(): void
    {
        // Тестируем fast-path: toggle говорит "не логировать" => interceptor только вызывает handler.

        $toggle = $this->createMock(InvocationInterface::class);
        $toggle
            ->expects($this->once())
            ->method('shouldLogAt')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger->expects($this->never())->method('warning');

        // создаем главное - сам интерцептор, то что и тестируем
        $interceptor = new DiagnosticsInterceptor('callable', $logger, $toggle);

        $context = $this->createMock(CallContextInterface::class);

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($context)
            ->willReturn($expected = ['ok' => true]);

        $this->assertSame($expected, $interceptor->intercept($context, $handler));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\DiagnosticsInterceptor::intercept
     */
    #[Test]
    #[TestDox('intercept: если диагностика активна и handler успешен, пишет info событие и возвращает результат')]
    public function testInterceptLogsInfoAndReturnsResultOnSuccess(): void
    {
        // Тестируем happy-path: toggle разрешает логирование, handler возвращает результат.

        MemoryGetUsageMock::setDefault(1000);
        MemoryGetPeakUsageMock::setDefault(2000);

        $toggle = $this->createMock(InvocationInterface::class);
        $toggle
            ->expects($this->once())
            ->method('shouldLogAt')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(true);

        $context = $this->createMock(CallContextInterface::class);
        $context
            ->expects($this->once())
            ->method('getTarget')
            ->willReturn(Target::fromPathString('Some\\Action::run', '::'));
        $context->expects($this->never())->method('getAttribute');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('diagnostics.action', $this->isType('array'));

        // создаем главное - сам интерцептор, то что и тестируем
        $interceptor = new DiagnosticsInterceptor('callable', $logger, $toggle);

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($context)
            ->willReturn($expected = 'result');

        $this->assertSame($expected, $interceptor->intercept($context, $handler));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\DiagnosticsInterceptor::intercept
     */
    #[Test]
    #[TestDox('intercept: если target пустой, берёт action из callable_attribute и пишет info')]
    public function testInterceptResolvesActionFromCallableAttributeWhenTargetIsEmpty(): void
    {
        MemoryGetUsageMock::setDefault(1000);
        MemoryGetPeakUsageMock::setDefault(2000);

        $toggle = $this->createMock(InvocationInterface::class);
        $toggle
            ->expects($this->once())
            ->method('shouldLogAt')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(true);

        $context = $this->createMock(CallContextInterface::class);
        $context
            ->expects($this->once())
            ->method('getTarget')
            ->willReturn(Target::fromPathArray([]));
        $context
            ->expects($this->once())
            ->method('getAttribute')
            ->with('callable')
            ->willReturn('Some\\Action::run');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('diagnostics.action', $this->isType('array'));

        $interceptor = new DiagnosticsInterceptor('callable', $logger, $toggle);

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($context)
            ->willReturn($expected = 'result');

        $this->assertSame($expected, $interceptor->intercept($context, $handler));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\DiagnosticsInterceptor::intercept
     */
    #[Test]
    #[TestDox('intercept: если target пустой и callable не распознан, пишет action=unknown')]
    public function testInterceptUsesUnknownActionWhenCallableIsNotResolvable(): void
    {
        MemoryGetUsageMock::setDefault(1000);
        MemoryGetPeakUsageMock::setDefault(2000);

        $toggle = $this->createMock(InvocationInterface::class);
        $toggle
            ->expects($this->once())
            ->method('shouldLogAt')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(true);

        $context = $this->createMock(CallContextInterface::class);
        $context
            ->expects($this->once())
            ->method('getTarget')
            ->willReturn(Target::fromPathArray([]));
        $context
            ->expects($this->once())
            ->method('getAttribute')
            ->with('callable')
            ->willReturn(new \stdClass());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'diagnostics.action',
                $this->callback(static fn(array $event): bool => ($event['action'] ?? null) === 'unknown'),
            );

        $interceptor = new DiagnosticsInterceptor('callable', $logger, $toggle);

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($context)
            ->willReturn($expected = 'result');

        $this->assertSame($expected, $interceptor->intercept($context, $handler));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\DiagnosticsInterceptor::intercept
     */
    #[Test]
    #[TestDox('intercept: если диагностика активна и handler бросает исключение, пишет warning событие и пробрасывает исключение')]
    public function testInterceptLogsWarningAndRethrowsOnException(): void
    {
        // Тестируем ветку catch: warning логируется, исключение пробрасывается наружу.

        MemoryGetUsageMock::setDefault(1000);
        MemoryGetPeakUsageMock::setDefault(2000);

        $toggle = $this->createMock(InvocationInterface::class);
        $toggle
            ->expects($this->once())
            ->method('shouldLogAt')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(true);

        $context = $this->createMock(CallContextInterface::class);
        $context
            ->expects($this->once())
            ->method('getTarget')
            ->willReturn(Target::fromPathString('Some\\Action::run', '::'));
        $context->expects($this->never())->method('getAttribute');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->isType('string'), $this->isType('array'));

        $interceptor = new DiagnosticsInterceptor('callable', $logger, $toggle);

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($context)
            ->willThrowException(new \RuntimeException('boom', 123));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $interceptor->intercept($context, $handler);
    }
}
