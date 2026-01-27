<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Runtime;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsException;

/**
 * Filesystem-хранилище runfile (runtime override-переключателя диагностики).
 *
 * Единая точка работы с runfile:
 * - чтение (loadRunfileOrNull)
 * - атомарная запись (saveRunfileOrFail)
 * - удаление (removeRunfileOrFalse)
 *
 * Политика чтения намеренно «мягкая»: любая ошибка I/O/парсинга/валидации трактуется как null,
 * чтобы interceptor/команды не падали из-за битого файла.
 */
final class RunfileRepository
{
    /**
     * @param non-empty-string $pathToFile Путь до runfile.
     */
    public function __construct(public readonly string $pathToFile) {}

    /**
     * Загружает текущий runtime override-конфиг диагностики.
     *
     * @return Runfile|null
     *   - Runfile, если файл существует и корректно распарсен;
     *   - null, если файла нет, он недоступен, имеет невалидный JSON/формат или не проходит валидацию модели.
     */
    public function loadRunfileOrNull(): ?Runfile
    {
        if (!is_file($this->pathToFile)) {
            return null;
        }

        $raw = file_get_contents($this->pathToFile);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $enabled = (bool)($data['enabled'] ?? false);

        if (!isset($data['started_at'])) {
            return null;
        }
        if ((!is_string($data['started_at'])) or (trim($data['started_at']) === '')) {
            return null;
        }

        try {
            $startedAt = new \DateTimeImmutable($data['started_at']);
        } catch (\Throwable) {
            return null;
        }

        $until = null;
        if (array_key_exists('until', $data) and ($data['until'] !== null)) {
            // until должен быть непустой строкой, парсибельной DateTimeImmutable.
            if ((!is_string($data['until'])) or (trim($data['until']) === '')) {
                return null;
            }

            try {
                $until = new \DateTimeImmutable($data['until']);
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return new Runfile(isEnabled: $enabled, startedAt: $startedAt, until: $until);
        } catch (\Throwable) {
            // Любая логическая ошибка/валидация трактуется как "невалидный runfile".
            return null;
        }
    }

    /**
     * Сохраняет runfile на диск.
     *
     * Запись выполняется атомарно (временный файл + rename), чтобы воркеры не читали
     * частично записанный JSON.
     *
     * @throws DiagnosticsException Если не удалось сериализовать payload или выполнить запись/replace.
     */
    public function saveRunfileOrFail(Runfile $runfile): void
    {
        $tmp = $this->makeTmpPath($this->pathToFile);

        $payload = [
            'enabled' => $runfile->isEnabled,
            'started_at' => $runfile->startedAt->format(\DateTimeInterface::ATOM),
            'until' => $runfile->until?->format(\DateTimeInterface::ATOM),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            DiagnosticsException::throwRunfileSerializeException();
        }

        if (file_put_contents($tmp, $json) === false) {
            DiagnosticsException::throwRunfileTempWriteException($tmp);
        }

        if (!rename($tmp, $this->pathToFile)) {
            unlink($tmp);
            DiagnosticsException::throwRunfileReplaceException($this->pathToFile);
        }
    }

    /**
     * Останавливает диагностику, удаляя runfile.
     *
     * @return bool true, если файл был фактически удалён.
     */
    public function removeRunfileOrFalse(): bool
    {
        if (!is_file($this->pathToFile)) {
            return false;
        }

        return unlink($this->pathToFile);
    }

    /**
     * Генерирует путь для временного файла, используемого при атомарной записи.
     *
     * @param non-empty-string $path Основной путь runfile.
     */
    private function makeTmpPath(string $path): string
    {
        return sprintf("%s.%s.tmp", $path, str_replace('.', '', uniqid('', true)));
    }
}