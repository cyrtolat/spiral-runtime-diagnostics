<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

require_once __DIR__ . '/Mock/mock_functions_kit.php';

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsInvocation;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\FileGetContentsMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\IsFileMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\JsonDecodeMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiagnosticsInvocation::class)]
#[TestDox('DiagnosticsInvocation: решение логировать ли вызов на основе runfile')]
final class DiagnosticsInvocationTest extends TestCase
{
    private string $runfilePath;

    protected function setUp(): void
    {
        $this->runfilePath = sys_get_temp_dir() . '/spiral-runtime-diagnostics-tests/runfile-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        IsFileMock::reset();
        FileGetContentsMock::reset();
        JsonDecodeMock::reset();
    }

    private function buildInvocation(): DiagnosticsInvocation
    {
        return new DiagnosticsInvocation(new RunfileRepository($this->runfilePath));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsInvocation::shouldLogAt
     */
    #[Test]
    #[TestDox('shouldLogAt: возвращает false, если runfile отсутствует или не может быть загружен')]
    public function testShouldLogAtReturnsFalseWhenRunfileIsMissing(): void
    {
        IsFileMock::when($this->runfilePath, false);

        $this->assertFalse($this->buildInvocation()->shouldLogAt(new \DateTimeImmutable()));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsInvocation::shouldLogAt
     */
    #[Test]
    #[TestDox('shouldLogAt: возвращает true, если runfile включен и действует бессрочно (until=null)')]
    public function testShouldLogAtReturnsTrueWhenEnabledForever(): void
    {
        IsFileMock::when($this->runfilePath, true);

        $at = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');

        FileGetContentsMock::when($this->runfilePath, \json_encode([
            'started_at' => $at->modify('-1 minute')->format(\DateTimeInterface::ATOM),
            'until' => null,
        ]));

        $this->assertTrue($this->buildInvocation()->shouldLogAt($at));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsInvocation::shouldLogAt
     */
    #[Test]
    #[TestDox('shouldLogAt: возвращает false, если runfile истёк (at >= until)')]
    public function testShouldLogAtReturnsFalseWhenExpired(): void
    {
        IsFileMock::when($this->runfilePath, true);

        $at = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');

        FileGetContentsMock::when($this->runfilePath, \json_encode([
            'started_at' => $at->modify('-1 hour')->format(\DateTimeInterface::ATOM),
            'until' => $at->modify('-1 second')->format(\DateTimeInterface::ATOM),
        ]));

        $this->assertFalse($this->buildInvocation()->shouldLogAt($at));
    }
}
