<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

use Cyrtolat\SpiralRuntimeDiagnostics\Support\CallableNameFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableNameFormatter::class)]
#[TestDox('CallableNameFormatter: best-effort имя callable для диагностики')]
final class CallableNameFormatterTest extends TestCase
{
    // =================================================================================================================
    // parseCallable()
    // =================================================================================================================

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: возвращает пустую строку для null')]
    public function testToStringReturnsEmptyForNull(): void
    {
        $this->assertSame('', CallableNameFormatter::parseCallable(null));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: форматирует callable-массив [object, method] как Class::method')]
    public function testToStringFormatsArrayCallableWithObject(): void
    {
        $callable = [new DummyCallable(), 'run'];

        $this->assertSame(DummyCallable::class . '::run', CallableNameFormatter::parseCallable($callable));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: форматирует callable-массив [ClassName::class, method] как ClassName::method')]
    public function testToStringFormatsArrayCallableWithClassString(): void
    {
        $callable = [DummyCallable::class, 'run'];

        $this->assertSame(DummyCallable::class . '::run', CallableNameFormatter::parseCallable($callable));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: форматирует invokable object как Class::__invoke')]
    public function testToStringFormatsInvokableObject(): void
    {
        $this->assertSame(
            DummyInvokable::class . '::__invoke',
            CallableNameFormatter::parseCallable(new DummyInvokable())
        );
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: возвращает строковый callable как есть')]
    public function testToStringReturnsStringCallableAsIs(): void
    {
        $this->assertSame('some_function', CallableNameFormatter::parseCallable('some_function'));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: форматирует closure в формате ScopeClass::{closure}, если scope class известен')]
    public function testToStringFormatsClosureWithScopeClass(): void
    {
        $closure = (new DummyScopeForClosure())->makeClosure();

        $this->assertSame(DummyScopeForClosure::class . '::{closure}', CallableNameFormatter::parseCallable($closure));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: форматирует closure в виде {closure}, если scope class неизвестен')]
    public function testToStringFormatsClosureWithoutScopeClass(): void
    {
        $closure = makeClosureWithoutScopeClass();

        $this->assertSame('{closure}', CallableNameFormatter::parseCallable($closure));
    }

    /**
     * @covers \\Cyrtolat\\SpiralRuntimeDiagnostics\\Helpers\\CallableNameFormatter::parseCallable
     */
    #[Test]
    #[TestDox('toString: возвращает пустую строку для неподдерживаемого типа')]
    public function testToStringReturnsEmptyForUnsupportedType(): void
    {
        $this->assertSame('', CallableNameFormatter::parseCallable(new \stdClass()));
    }
}

final class DummyCallable
{
    public function run(): void {}
}

final class DummyInvokable
{
    public function __invoke(): void {}
}

final class DummyScopeForClosure
{
    public function makeClosure(): \Closure
    {
        return function (): void {};
    }
}

function makeClosureWithoutScopeClass(): \Closure
{
    return static function (): void {};
}
