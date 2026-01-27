<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Console;

/**
 * Информационная команда по диагностике.
 *
 * Команда предназначена для операторов/разработчиков: объясняет, что делает диагностика,
 * когда её включать и как правильно выключать.
 */
final class InfoCommand extends AbstractCommand
{
    protected const NAME = 'diag:info';
    protected const DESCRIPTION = 'Справка: что такое диагностика и как ей пользоваться.';

    public function perform(): int
    {
        $this->writeln('<comment>Что это:</comment>');
        $this->writeln('Режим, который позволяет временно включить подробное логирование вызовов (через interceptor) без деплоя и без перезапуска воркеров.');
        $this->writeln('');

        $this->writeln('<comment>Зачем нужно:</comment>');
        $this->writeln('- быстро получить факты в проде при инциденте (время выполнения, успех/ошибка, базовые метрики памяти);');
        $this->writeln('- зафиксировать последовательность проблемных вызовов, когда обычных логов недостаточно;');
        $this->writeln('- сделать это контролируемо по времени (TTL) и отключить обратно одной командой.');
        $this->writeln('');

        $this->writeln('<comment>Когда применять:</comment>');
        $this->writeln('- если наблюдаются ошибки/таймауты и нужно понять, какие именно вызовы и как долго выполняются;');
        $this->writeln('- если проблема плавающая и сложно воспроизводится локально;');
        $this->writeln('- если нужен быстрый "черный ящик" без изменения кода.');
        $this->writeln('');

        $this->writeln('<comment>Как пользоваться:</comment>');
        $this->writeln('- конфигурация: <comment>diag:config</comment>');
        $this->writeln('- запустить: <comment>diag:start --duration=5m</comment>');
        $this->writeln('- проверить: <comment>diag:status</comment>');
        $this->writeln('- остановить: <comment>diag:stop</comment>');
        $this->writeln('');

        $this->writeln('<comment>Важно:</comment>');
        $this->writeln('- режим увеличивает объём логов (может быть много записей);');
        $this->writeln('- не включайте "бессрочно" без необходимости; если включили — обязательно выключите вручную.');
        $this->writeln('');

        return self::SUCCESS;
    }
}
