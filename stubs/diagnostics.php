<?php

declare(strict_types=1);

/**
 * Конфигурация пакета Cyrtolat\SpiralRuntimeDiagnostics.
 *
 * Этот файл можно (опционально) опубликовать в приложение через `diag:publish --write`.
 * Если файл не опубликован, значения всё равно будут подхвачены через defaults bootloader-а.
 */
return [
    // Длительность включения диагностики по умолчанию для `diag:start`.
    'duration' => env('DIAGNOSTICS_DURATION', '1h'), // Формат: "5m", "1h", "30s", "2d" или "forever".

    // Путь к runfile, который создаётся/удаляется командами diag:start/diag:stop.
    'runfile_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'diagnostics.json',

    // Имя канала логирования для диагностических событий.
    'log_channel' => env('DIAGNOSTICS_LOG_CHANNEL', 'diagnostics'),

    // Ключ, по которому в CallContext attributes хранится исходный callable.
    'callable_attribute' => 'callable',
];
