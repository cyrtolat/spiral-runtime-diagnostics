<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

use Cyrtolat\SpiralRuntimeDiagnostics\DiagnosticsConfiguration;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Публикует шаблон конфигурации diagnostics.php в хост-приложение.
 *
 * Команда опциональна: пакет работает и без опубликованного файла,
 * т.к. defaults задаются в bootloader-е.
 */
final class PublishCommand extends AbstractCommand
{
    protected const NAME = 'diag:publish';
    protected const DESCRIPTION = 'Опубликовать шаблон конфигурации diagnostics.php.';

    protected const SIGNATURE = 'diag:publish
        {--write : Записать файл на диск. Без этого флага команда только покажет, что будет опубликовано.}
        {--force : Перезаписать существующий файл (alias: --overwrite).}
        {--overwrite : Алиас для --force.}
        {--path= : Путь назначения (например @config/diagnostics.php или /абсолютный/путь). По умолчанию @config/diagnostics.php.}
    ';

    public function perform(FilesInterface $files, DirectoriesInterface $directories, OutputInterface $output): int
    {
        $target = (string)($this->option('path') ?? '');
        if (\trim($target) === '') {
            $target = '@config/diagnostics.php';
        }

        $force = (bool)($this->option('force') ?? false) || (bool)($this->option('overwrite') ?? false);
        $write = (bool)($this->option('write') ?? false);

        $resolvedTarget = $this->resolveDirectoriesAliases($target, $directories);
        $resolvedTarget = $files->normalizePath($resolvedTarget);

        $stubPath = DiagnosticsConfiguration::getStubFilePath();
        $contents = $files->read($stubPath);

        $this->writeln('<info>Diagnostics: publish config</info>');
        $this->writeln('');
        $this->writeln('<comment>Source:</comment> ' . $stubPath);
        $this->writeln('<comment>Target:</comment> ' . $resolvedTarget);
        $this->writeln('');

        if (!$write) {
            $this->writeln('<comment>Dry run.</comment> Добавь флаг <comment>--write</comment>, чтобы записать файл.');
            $this->writeln('');

            $output->write($contents . "\n", false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        if ($files->exists($resolvedTarget) && !$force) {
            $this->error('Файл уже существует. Используй --force (или --overwrite), чтобы перезаписать.');
            return self::FAILURE;
        }

        $files->write(
            filename: $resolvedTarget,
            data: $contents,
            mode: FilesInterface::READONLY,
            ensureDirectory: true,
        );

        $this->writeln('<fg=green>OK:</> файл опубликован.');

        return self::SUCCESS;
    }

    private function resolveDirectoriesAliases(string $path, DirectoriesInterface $directories): string
    {
        foreach ($directories->getAll() as $alias => $value) {
            $path = \str_replace(\sprintf('@%s', $alias), $value, $path);
        }

        return $path;
    }
}
