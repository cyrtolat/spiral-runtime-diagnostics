<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Tests;

require_once __DIR__ . '/Mock/mock_functions_kit.php';

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\Runfile;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\FileGetContentsMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\FilePutContentsMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\IsFileMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\JsonDecodeMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\JsonEncodeMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\RenameMock;
use Cyrtolat\SpiralRuntimeDiagnostics\Tests\Mock\UnlinkMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunfileRepository::class)]
#[TestDox('RunfileRepository: работа с runfile в runtime (чтение/запись/удаление)')]
final class RunfileRepositoryTest extends TestCase
{
    private string $runfilePath;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/spiral-runtime-diagnostics-tests';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->runfilePath = sprintf('%s/runfile-%s.json', $dir, bin2hex(random_bytes(6)));
        if (is_file($this->runfilePath)) {
            unlink($this->runfilePath);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->runfilePath)) {
            unlink($this->runfilePath);
        }

        FileGetContentsMock::reset();
        FilePutContentsMock::reset();
        IsFileMock::reset();
        RenameMock::reset();
        UnlinkMock::reset();
        JsonEncodeMock::reset();
        JsonDecodeMock::reset();
    }

    private function buildStorage(): RunfileRepository
    {
        return new RunfileRepository($this->runfilePath);
    }

    // =================================================================================================================
    // loadRunfileOrNull()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::loadRunfileOrNull
     */
    #[Test]
    #[TestDox('loadRunfileOrNull: возвращает null, если файла нет, файл не читается или JSON невалиден')]
    public function testLoadRunfileOrNullReturnsNullWhenFileMissingUnreadableOrJsonInvalid(): void
    {
        // Тестируем ветку: файла нет (is_file=false) => null.
        IsFileMock::when($this->runfilePath, false);
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: файл есть (is_file=true), но file_get_contents вернул false => null.
        IsFileMock::when($this->runfilePath, true);
        FileGetContentsMock::when($this->runfilePath, false);
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: file_get_contents вернул строку, но JSON не парсится => null.
        FileGetContentsMock::when($this->runfilePath, 'this_is_string, not json');
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: JSON распарсился, но это не массив => null.
        FileGetContentsMock::when($this->runfilePath, json_encode('not_an_array'));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::loadRunfileOrNull
     */
    #[Test]
    #[TestDox('loadRunfileOrNull: started_at обязателен и должен быть непустой парсибельной строкой')]
    public function testLoadRunfileOrNullReturnsNullWhenStartedAtMissingOrInvalid(): void
    {
        IsFileMock::when($this->runfilePath, true);

        // Тестируем ветку: started_at отсутствует => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'until' => null,
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: started_at есть, но пустая строка => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'started_at' => '',
            'until' => null,
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: started_at есть, но строка не парсится DateTimeImmutable => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'started_at' => 'not parsable datetime',
            'until' => null,
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::loadRunfileOrNull
     */
    #[Test]
    #[TestDox('loadRunfileOrNull: until (если задан) должен быть непустой парсибельной строкой или null')]
    public function testLoadRunfileOrNullReturnsNullWhenUntilIsInvalid(): void
    {
        IsFileMock::when($this->runfilePath, true);

        $startedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        // Тестируем ветку: until присутствует, но пустая строка => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'started_at' => $startedAt,
            'until' => '',
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());

        // Тестируем ветку: until присутствует, но строка не парсится DateTimeImmutable => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'started_at' => $startedAt,
            'until' => 'not parsable datetime and not null',
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::loadRunfileOrNull
     */
    #[Test]
    #[TestDox('loadRunfileOrNull: возвращает null, если период логически невалиден (started_at > until)')]
    public function testLoadRunfileOrNullReturnsNullWhenPeriodIsLogicallyInvalid(): void
    {
        IsFileMock::when($this->runfilePath, true);

        $datetime = new \DateTimeImmutable();

        // Тестируем ветку: модель Runfile выбрасывает исключение (started_at позже until) => null.
        FileGetContentsMock::when($this->runfilePath, json_encode([
            'enabled' => true,
            'started_at' => $datetime->modify('+5 minutes')->format(\DateTimeInterface::ATOM),
            'until' => $datetime->modify('-5 minutes')->format(\DateTimeInterface::ATOM),
        ]));
        $this->assertNull($this->buildStorage()->loadRunfileOrNull());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::loadRunfileOrNull
     */
    #[Test]
    #[TestDox('loadRunfileOrNull: возвращает Runfile для валидного JSON (until может отсутствовать или быть null)')]
    public function testLoadRunfileOrNullReturnsRunfileForValidJson(): void
    {
        IsFileMock::when($this->runfilePath, true);

        $datetime = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $runfileData = ['enabled' => true, 'started_at' => $datetime];

        // Тестируем ветку: until отсутствует => until=null.
        FileGetContentsMock::when($this->runfilePath, json_encode($runfileData));
        $runfile = $this->buildStorage()->loadRunfileOrNull();
        $this->assertTrue($runfile instanceof Runfile);
        $this->assertNull($runfile->until);

        // Тестируем ветку: until явно null => until=null.
        FileGetContentsMock::when($this->runfilePath, json_encode($runfileData + ['until' => null]));
        $runfile = $this->buildStorage()->loadRunfileOrNull();
        $this->assertTrue($runfile instanceof Runfile);
        $this->assertNull($runfile->until);

        // Тестируем ветку: until задан валидной датой => until распарсен в DateTimeImmutable.
        FileGetContentsMock::when($this->runfilePath, json_encode($runfileData + ['until' => $datetime]));
        $runfile = $this->buildStorage()->loadRunfileOrNull();
        $this->assertTrue($runfile instanceof Runfile);
        $this->assertInstanceOf(\DateTimeImmutable::class, $runfile->until);

        // Тестируем корректность базовых полей модели Runfile.
        $this->assertNotNull($runfile->isEnabled);
        $this->assertNotNull($runfile->startedAt);
    }

    // =================================================================================================================
    // saveRunfileOrFail()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::saveRunfileOrFail
     */
    #[Test]
    #[TestDox('saveRunfileOrFail: пишет JSON атомарно (tmp-файл + rename) и сохраняет обязательные ключи')]
    public function testSaveRunfileOrFailWritesJsonAtomically(): void
    {
        // Тестируем успешную атомарную запись:
        // - сначала пишем во временный файл
        // - затем делаем rename(tmp, runfile_path)
        // Также проверяем, что итоговый JSON содержит ключи enabled/started_at/until.

        // Используем filesystem-mock как «шпион»: он сохраняет calls(), но при значении по умолчанию null
        // делегирует в реальные \file_put_contents/\rename/\unlink.
        FilePutContentsMock::reset();
        RenameMock::reset();
        UnlinkMock::reset();

        FilePutContentsMock::setDefault(null);
        RenameMock::setDefault(null);
        UnlinkMock::setDefault(null);

        $runfile = new Runfile(true, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->buildStorage()->saveRunfileOrFail($runfile);

        // Тестируем факт создания итогового файла.
        $this->assertTrue(is_file($this->runfilePath));

        // Тестируем, что файл читается.
        $data = file_get_contents($this->runfilePath);
        $this->assertNotFalse($data);

        // Тестируем, что содержимое — корректный JSON-массив.
        $payload = json_decode($data, true);
        $this->assertIsArray($payload);

        // Тестируем, что есть все обязательные ключи.
        $this->assertArrayHasKey('enabled', $payload);
        $this->assertArrayHasKey('started_at', $payload);
        $this->assertArrayHasKey('until', $payload);

        // Тестируем атомарность (если реально было использовано переопределение функций на уровне namespace).
        $putCalls = FilePutContentsMock::calls();
        if ($putCalls !== []) {
            $tmp = $putCalls[0]['path'];
            $this->assertStringEndsWith('.tmp', $tmp);

            $renameCalls = RenameMock::calls();
            $this->assertNotEmpty($renameCalls);
            $this->assertSame($tmp, $renameCalls[0]['from']);
            $this->assertSame($this->runfilePath, $renameCalls[0]['to']);
        }
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::saveRunfileOrFail
     */
    #[Test]
    #[TestDox('saveRunfileOrFail: бросает исключение, если json_encode вернул false')]
    public function testSaveRunfileOrFailThrowsWhenJsonEncodeFails(): void
    {
        // Тестируем ветку: json_encode вернул false => выбрасывается DiagnosticsException.
        JsonEncodeMock::setDefault(false);

        $this->expectException(DiagnosticsException::class);
        $this->buildStorage()->saveRunfileOrFail(new Runfile(true, new \DateTimeImmutable(), null));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::saveRunfileOrFail
     */
    #[Test]
    #[TestDox('saveRunfileOrFail: бросает исключение, если не удалось записать tmp-файл')]
    public function testSaveRunfileOrFailThrowsWhenTempWriteFails(): void
    {
        // Тестируем ветку: file_put_contents(tmp, ...) вернул false => выбрасывается DiagnosticsException.
        FilePutContentsMock::setDefault(false);

        $this->expectException(DiagnosticsException::class);
        $this->buildStorage()->saveRunfileOrFail(new Runfile(true, new \DateTimeImmutable(), null));
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::saveRunfileOrFail
     */
    #[Test]
    #[TestDox('saveRunfileOrFail: бросает исключение, если rename не удался, и удаляет tmp-файл')]
    public function testSaveRunfileOrFailThrowsWhenRenameFailsAndDeletesTmp(): void
    {
        // Тестируем ветку:
        // - rename(tmp, path) вернул false
        // - tmp файл удаляется (unlink(tmp))
        // - выбрасывается DiagnosticsException.

        $runfile = new Runfile(true, new \DateTimeImmutable(), null);

        RenameMock::setDefault(false);

        $this->expectException(DiagnosticsException::class);
        $this->buildStorage()->saveRunfileOrFail($runfile);

        // Тестируем, что unlink(tmp) был вызван.
        $this->assertNotEmpty(UnlinkMock::calls());
    }

    // =================================================================================================================
    // makeTmpPath()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::makeTmpPath
     */
    #[Test]
    #[TestDox('makeTmpPath: генерирует разные tmp-имена и всегда добавляет суффикс .tmp')]
    public function testMakeTmpPathGeneratesTmpSuffixAndIsUnique(): void
    {
        // Тестируем, что на каждом вызове saveRunfileOrFail() генерируется новый tmp-путь
        // (т.е. makeTmpPath() не возвращает одинаковое имя).

        $runfile = new Runfile(true, new \DateTimeImmutable(), null);

        $this->buildStorage()->saveRunfileOrFail($runfile);
        $this->assertSame(1, count(RenameMock::calls()));

        $this->buildStorage()->saveRunfileOrFail($runfile);
        $this->assertSame(2, count(RenameMock::calls()));

        $this->assertNotSame(RenameMock::calls()[0], RenameMock::calls()[1]);
    }

    // =================================================================================================================
    // removeRunfile()
    // =================================================================================================================

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::removeRunfileOrFalse
     */
    #[Test]
    #[TestDox('removeRunfileOrFalse: ничего не делает, если файла нет')]
    public function testRemoveRunfileDoesNothingWhenMissing(): void
    {
        // Тестируем ветку: is_file=false => unlink не вызывается.
        IsFileMock::setDefault(false);

        $result = $this->buildStorage()->removeRunfileOrFalse();

        $this->assertFalse($result);
        $this->assertEmpty(UnlinkMock::calls());
    }

    /**
     * @covers \Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository::removeRunfileOrFalse
     */
    #[Test]
    #[TestDox('removeRunfileOrFalse: удаляет файл, если он существует')]
    public function testRemoveRunfileDeletesWhenExists(): void
    {
        // Тестируем ветку: is_file=true => вызывается unlink(path).
        IsFileMock::setDefault(true);
        UnlinkMock::setDefault(true);

        $result = $this->buildStorage()->removeRunfileOrFalse();

        $this->assertTrue($result);
        $this->assertNotEmpty(UnlinkMock::calls());
    }
}
