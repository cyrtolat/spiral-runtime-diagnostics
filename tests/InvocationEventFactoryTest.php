<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

require_once __DIR__ . '/Mock/mock_functions_kit.php';

use Cyrtolat\SpiralRuntimeDiagnostics\Support\InvocationEventFactory;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetPeakUsageMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\MemoryGetUsageMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvocationEventFactory::class)]
#[TestDox('InvocationEventFactory: ')]
final class InvocationEventFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        MemoryGetUsageMock::reset();
        MemoryGetPeakUsageMock::reset();
    }

    // =================================================================================================================
    // make()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\InvocationEventFactory::make
     */
    #[Test]
    #[TestDox('make: возвращает payload с ожидаемыми ключами и значениями')]
    public function testMakeReturnsPayloadWithExpectedKeysAndValues(): void
    {
        // Тестируем ветку: make() прокидывает бизнес-поля в модель как есть.
        // Также проверяем, что метрики памяти запрашиваются с real_usage=true.

        // Делаем данные по памяти детерминированными.
        MemoryGetUsageMock::setDefault(1000);
        MemoryGetPeakUsageMock::setDefault(2000);

        $event = InvocationEventFactory::make(
            action: $action = 'foo bar action',
            durationMs: $durationMs = 12345,
            ok: $ok = true,
        );

        // Тестируем прокидывание бизнес-полей.
        $this->assertSame($action, $event['action']);
        $this->assertSame($durationMs, $event['duration_ms']);
        $this->assertSame($ok, $event['ok']);

        // Тестируем, что функции памяти вызываются с параметром true.
        $this->assertSame([['real_usage' => true]], MemoryGetUsageMock::calls());
        $this->assertSame([['real_usage' => true]], MemoryGetPeakUsageMock::calls());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Process\DiagnosticsEvent::make
     */
    #[Test]
    #[TestDox('make: форматирует память в человекочитаемый вид (B/KB/MB/GB/TB)')]
    public function testMakeFormatsBytesHumanReadable(): void
    {
        // Тестируем форматирование через публичный make(), т.к. formatBytes() — приватный метод.
        // Сценарии ниже покрывают переходы между единицами (B -> KB -> MB -> GB -> TB).

        $args = ['', 0, true, 0, []];

        // Сценарий 1: байты (B).
        MemoryGetUsageMock::setDefault(123);
        MemoryGetPeakUsageMock::setDefault(123);
        $event = InvocationEventFactory::make(...$args);
        $this->assertSame('123 B', $event['memory_usage']);
        $this->assertSame('123 B', $event['memory_peak']);

        // Сценарий 2: килобайты (KB) с одной цифрой после запятой.
        MemoryGetUsageMock::setDefault(123456);
        MemoryGetPeakUsageMock::setDefault(123456);
        $event = InvocationEventFactory::make(...$args);
        $this->assertSame('120.6 KB', $event['memory_usage']);
        $this->assertSame('120.6 KB', $event['memory_peak']);

        // Сценарий 3: мегабайты (MB).
        MemoryGetUsageMock::setDefault(123456789);
        MemoryGetPeakUsageMock::setDefault(123456789);
        $event = InvocationEventFactory::make(...$args);
        $this->assertSame('117.7 MB', $event['memory_usage']);
        $this->assertSame('117.7 MB', $event['memory_peak']);

        // Сценарий 4: гигабайты (GB) без лишних нулей ("115 GB", а не "115.0 GB").
        MemoryGetUsageMock::setDefault(123456789012);
        MemoryGetPeakUsageMock::setDefault(123456789012);
        $event = InvocationEventFactory::make(...$args);
        $this->assertSame('115 GB', $event['memory_usage']);
        $this->assertSame('115 GB', $event['memory_peak']);

        // Сценарий 5: терабайты (TB).
        MemoryGetUsageMock::setDefault(123456789012345);
        MemoryGetPeakUsageMock::setDefault(123456789012345);
        $event = InvocationEventFactory::make(...$args);
        $this->assertSame('112.3 TB', $event['memory_usage']);
        $this->assertSame('112.3 TB', $event['memory_peak']);
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Process\DiagnosticsEvent::make
     */
    #[Test]
    #[TestDox('make: если память отрицательная, нормализует её до 0 и форматирует как 0 B')]
    public function testMakeNormalizesNegativeBytesToZero(): void
    {
        // Тестируем защиту в formatBytes(): если bytes < 0, то bytes приводится к 0.

        MemoryGetUsageMock::setDefault(-123);
        MemoryGetPeakUsageMock::setDefault(-123);

        $event = InvocationEventFactory::make('', 123, true, 0, []);

        $this->assertSame('0 B', $event['memory_usage']);
        $this->assertSame('0 B', $event['memory_peak']);
    }
}
