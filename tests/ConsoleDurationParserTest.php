<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;
use Cyrtolat\SpiralRuntimeDiagnostics\Support\ConsoleDurationParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleDurationParser::class)]
#[TestDox('ConsoleDurationParser: парсинг длительности (TTL) в момент отключения диагностики')]
final class ConsoleDurationParserTest extends TestCase
{
    private function buildParser(): ConsoleDurationParser
    {
        return new ConsoleDurationParser();
    }

    // =================================================================================================================
    // parseUntil()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: возвращает null для пустого значения и для forever')]
    public function testParseUntilReturnsNullForEmptyOrForever(): void
    {
        // Тестируем ветку: пустая строка означает «бессрочно» => null.
        $this->assertNull($this->buildParser()->parseUntil('', new \DateTimeImmutable()));

        // Тестируем ветку: forever означает «бессрочно» => null.
        $this->assertNull($this->buildParser()->parseUntil('forever', new \DateTimeImmutable()));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: поддерживает единицы s/m/h/d и рассчитывает until относительно now')]
    public function testParseUntilSupportsUnits(): void
    {
        $now = new \DateTimeImmutable();

        // Тестируем ветку: секунды (s) => now + N seconds.
        $this->assertEquals($now->modify('+30 seconds'), $this->buildParser()->parseUntil('30s', $now));

        // Тестируем ветку: минуты (m) => now + N minutes.
        $this->assertEquals($now->modify('+5 minutes'), $this->buildParser()->parseUntil('5m', $now));

        // Тестируем ветку: часы (h) => now + N hours.
        $this->assertEquals($now->modify('+1 hours'), $this->buildParser()->parseUntil('1h', $now));

        // Тестируем ветку: дни (d) => now + N days.
        $this->assertEquals($now->modify('+2 days'), $this->buildParser()->parseUntil('2d', $now));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: бросает исключение при неверном формате длительности (например, foo)')]
    public function testParseUntilThrowsForInvalidFormatFoo(): void
    {
        // Тестируем ветку: строка не соответствует шаблону ^(\d+)([smhd])$ => DiagnosticsException.
        $this->expectException(DiagnosticsException::class);
        $this->buildParser()->parseUntil('foo', new \DateTimeImmutable());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: бросает исключение при неверном формате длительности (например, -30s)')]
    public function testParseUntilThrowsForInvalidFormatNegativeNumber(): void
    {
        // Тестируем ветку: «-30s» не проходит regex (минус не допускается) => DiagnosticsException.
        $this->expectException(DiagnosticsException::class);
        $this->buildParser()->parseUntil('-30s', new \DateTimeImmutable());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: бросает исключение при недопустимом значении длительности (например, 0s)')]
    public function testParseUntilThrowsForInvalidValueZero(): void
    {
        // Тестируем ветку: число распарсилось, но n <= 0 => DiagnosticsException.
        $this->expectException(DiagnosticsException::class);
        $this->buildParser()->parseUntil('0s', new \DateTimeImmutable());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Support\DurationParser::parseUntil
     */
    #[Test]
    #[TestDox('parseUntil: оборачивает ошибки DateInterval/DateTime в DiagnosticsException и сохраняет previous')]
    public function testParseUntilWrapsIntervalBuildErrors(): void
    {
        // Тестируем ветку catch в DurationParser::parseUntil().
        // Для этого передаём DateTimeImmutable-наследника, у которого add() всегда бросает исключение.
        $now = new ThrowingDateTimeImmutable('2025-01-01T00:00:00+00:00');

        try {
            $this->buildParser()->parseUntil('1s', $now);
            $this->fail('Ожидали DiagnosticsException, но исключение не было выброшено.');
        } catch (DiagnosticsException $e) {
            // Тестируем, что исходная причина не потерялась.
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('boom', $e->getPrevious()?->getMessage());
        }
    }
}

/**
 * Вспомогательный тестовый класс.
 *
 * Нужен для детерминированного воспроизведения ошибки в $now->add(...), чтобы попасть в catch в DurationParser.
 */
final class ThrowingDateTimeImmutable extends \DateTimeImmutable
{
    public function add(\DateInterval $interval): \DateTimeImmutable
    {
        throw new \RuntimeException('boom');
    }
}
