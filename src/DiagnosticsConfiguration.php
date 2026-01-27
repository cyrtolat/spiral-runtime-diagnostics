<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics;

use Spiral\Core\InjectableConfig;

/**
 * DI-конфигурация пакета диагностики ("первое реагирование").
 *
 * Источник данных: `config/diagnostics.php` в хост-приложении.
 *
 * Важно различать:
 * - **DiagnosticsConfig** — статические настройки (пути, канал логирования, дефолт TTL и т.д.).
 * - **Runfile** — runtime override, который создаётся/удаляется командами и реально включает/выключает
 *   диагностику для воркеров.
 */
final class DiagnosticsConfiguration extends InjectableConfig
{
    /**
     * Имя секции в конфиге Spiral.
     */
    public const CONFIG = 'diagnostics';

    private static ?array $defaults = null;

    public function __construct(array $config = [])
    {
        // InjectableConfig ожидает, что дефолты лежат в $this->config.
        // Чтобы не дублировать defaults в нескольких местах, держим их в stubs/diagnostics.php.
        $this->config = self::getDefaults();

        parent::__construct($config);
    }

    /**
     * Путь к stubs/diagnostics.php внутри пакета.
     */
    public static function getStubFilePath(): string
    {
        return dirname(__DIR__) . '/stubs/diagnostics.php';
    }

    /**
     * Дефолты, которые используются если в хост-приложении нет config/diagnostics.php.
     */
    public static function getDefaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        $defaults = require self::getStubFilePath();
        if (!is_array($defaults)) {
            throw new \RuntimeException('Невалидный (stub) конфиг диагностики: ожидался массив.');
        }

        return self::$defaults = $defaults;
    }

    public function getDuration(): string
    {
        $value = (string)($this->config['duration'] ?? '');
        $value = trim($value);

        return $value !== '' ? $value : (string)self::getDefaults()['duration'];
    }

    /**
     * Возвращает путь до runfile.
     *
     * @return non-empty-string
     */
    public function getRunfilePath(): string
    {
        $path = (string)($this->config['runfile_path'] ?? '');
        $path = trim($path);

        if ($path === '') {
            DiagnosticsException::throwRunfilePathNotConfigured();
        }

        return $path;
    }

    /**
     * @return non-empty-string
     */
    public function getLogChannel(): string
    {
        $value = (string)($this->config['log_channel'] ?? '');
        $value = trim($value);

        return $value !== '' ? $value : (string)self::getDefaults()['log_channel'];
    }

    /**
     * @return non-empty-string
     */
    public function getCallableAttribute(): string
    {
        $value = (string)($this->config['callable_attribute'] ?? '');
        $value = trim($value);

        return $value !== '' ? $value : (string)self::getDefaults()['callable_attribute'];
    }
}
