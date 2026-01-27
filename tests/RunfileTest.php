<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Runfile::class)]
#[TestDox('Runfile: модель состояния runtime-диагностики (активность/статусы/подписи)')]
final class RunfileTest extends TestCase
{
    // =================================================================================================================
    // __construct()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::__construct
     */
    #[Test]
    #[TestDox('Runfile::__construct: допускает until=null и until=startedAt (валидные значения периода)')]
    public function testConstructorAllowsNullUntilAndEqualDates(): void
    {
        $now = new \DateTimeImmutable();

        // Тестируем валидный случай: until=null — ограничение по времени отсутствует.
        $runfile = new Runfile(isEnabled: true, startedAt: $now, until: null);
        $this->assertInstanceOf(Runfile::class, $runfile);

        // Тестируем валидный случай: startedAt == until — период нулевой длительности допустим.
        $runfile = new Runfile(isEnabled: true, startedAt: $now, until: $now);
        $this->assertInstanceOf(Runfile::class, $runfile);

        // Тестируем валидный случай: startedAt может быть в будущем, если until=null.
        $runfile = new Runfile(isEnabled: true, startedAt: $now->modify('+5 minutes'), until: null);
        $this->assertInstanceOf(Runfile::class, $runfile);

        // Тестируем валидный случай: startedAt может быть в прошлом, если until=null.
        $runfile = new Runfile(isEnabled: true, startedAt: $now->modify('-5 minutes'), until: null);
        $this->assertInstanceOf(Runfile::class, $runfile);
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::__construct
     */
    #[Test]
    #[TestDox('Runfile::__construct: выбрасывает исключение, если startedAt позже until')]
    public function testConstructorThrowsWhenStartedAtIsAfterUntil(): void
    {
        $now = new \DateTimeImmutable();

        // Тестируем невалидный случай: startedAt > until должно приводить к исключению.
        $this->expectException(DiagnosticsException::class);
        new Runfile(isEnabled: true, startedAt: $now->modify('+5 minutes'), until: $now);
    }

    // =================================================================================================================
    // stateAt()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::stateAt
     */
    #[Test]
    #[TestDox('Runfile::stateAt: корректно определяет состояние (выключено/активно/завершено)')]
    public function testStateAtReturnsExpectedState(): void
    {
        $now = new \DateTimeImmutable();

        // Тестируем ветку: диагностика выключена (isEnabled=false) => STATE_OFF.
        $this->assertSame(
            expected: Runfile::STATE_OFF,
            actual: (new Runfile(
                isEnabled: false,
                startedAt: $now->modify('-5 minutes'),
                until: null,
            ))->stateAt($now),
        );

        // Тестируем ветку: диагностика включена (isEnabled=true) и until=null => STATE_ACTIVE.
        $this->assertSame(
            expected: Runfile::STATE_ACTIVE,
            actual: (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-5 minutes'),
                until: null,
            ))->stateAt($now),
        );

        // Тестируем ветку: диагностика включена и момент проверки раньше until => STATE_ACTIVE.
        $this->assertSame(
            expected: Runfile::STATE_ACTIVE,
            actual: (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-5 minutes'),
                until: $now->modify('+5 minutes'),
            ))->stateAt($now),
        );

        // Тестируем ветку: диагностика включена, но момент проверки позже until => STATE_EXPIRED.
        $this->assertSame(
            expected: Runfile::STATE_EXPIRED,
            actual: (new Runfile(
                isEnabled: true,
                startedAt: $now,
                until: $now->modify('+5 minutes'),
            ))->stateAt($now->modify('+10 minutes')),
        );
    }

    // =================================================================================================================
    // isActive()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::isActiveAt
     */
    #[Test]
    #[TestDox('Runfile::isActive: возвращает true только для активного состояния')]
    public function testIsActiveMatchesRules(): void
    {
        $now = new \DateTimeImmutable();

        // Тестируем ветку: isEnabled=false => isActive=false.
        $this->assertFalse(
            (new Runfile(
                isEnabled: false,
                startedAt: $now,
                until: null,
            ))->isActiveAt($now),
        );

        // Тестируем ветку: isEnabled=true и until=null => isActive=true.
        $this->assertTrue(
            (new Runfile(
                isEnabled: true,
                startedAt: $now,
                until: null,
            ))->isActiveAt($now),
        );

        // Тестируем поведение: startedAt в будущем не выключает активность, если until=null.
        $this->assertTrue(
            (new Runfile(
                isEnabled: true,
                startedAt: $now,
                until: null,
            ))->isActiveAt($now->modify('-5 minutes')),
        );

        // Тестируем ветку: isEnabled=true, но момент проверки позже until => isActive=false.
        $this->assertFalse(
            (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-10 minutes'),
                until: $now->modify('-5 minutes'),
            ))->isActiveAt($now),
        );
    }

    // =================================================================================================================
    // isExpiredAt()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::isExpiredAt
     */
    #[Test]
    #[TestDox('Runfile::isExpiredAt: возвращает true только для истёкшего состояния')]
    public function testIsExpiredMatchesRules(): void
    {
        $now = new \DateTimeImmutable();

        // isEnabled=false => state=off => isExpired=false.
        $this->assertFalse(
            (new Runfile(
                isEnabled: false,
                startedAt: $now->modify('-1 hour'),
                until: $now->modify('-10 minutes'),
            ))->isExpiredAt($now),
        );

        // isEnabled=true и until=null => never expired.
        $this->assertFalse(
            (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-1 hour'),
                until: null,
            ))->isExpiredAt($now),
        );

        // isEnabled=true и at < until => active => not expired.
        $this->assertFalse(
            (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-1 hour'),
                until: $now->modify('+10 minutes'),
            ))->isExpiredAt($now),
        );

        // isEnabled=true и at == until => expired.
        $this->assertTrue(
            (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-1 minute'),
                until: $now,
            ))->isExpiredAt($now),
        );

        // isEnabled=true и at > until => expired.
        $this->assertTrue(
            (new Runfile(
                isEnabled: true,
                startedAt: $now->modify('-1 hour'),
                until: $now->modify('-10 minutes'),
            ))->isExpiredAt($now),
        );
    }

    // =================================================================================================================
    // isExpiredAt()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile::isForever
     */
    #[Test]
    #[TestDox('Runfile::isForever: возвращает true если включено навсегда')]
    public function testisForeverMatchesRules(): void
    {
        $now = new \DateTimeImmutable();

        // until=null => forever (независимо от enabled).
        $this->assertTrue(
            (new Runfile(
                isEnabled: true,
                startedAt: $now,
                until: null,
            ))->isForever(),
        );

        $this->assertTrue(
            (new Runfile(
                isEnabled: false,
                startedAt: $now,
                until: null,
            ))->isForever(),
        );

        // until!=null => not forever.
        $this->assertFalse(
            (new Runfile(
                isEnabled: true,
                startedAt: $now,
                until: $now->modify('+1 hour'),
            ))->isForever(),
        );
    }
}
