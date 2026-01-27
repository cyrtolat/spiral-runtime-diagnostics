<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics;

use Cyrtolat\SpiralRuntimeDiagnostics\Console\ConfigCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Console\InfoCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Console\PublishCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Console\StartCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Console\StatusCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Console\StopCommand;
use Cyrtolat\SpiralRuntimeDiagnostics\Runtime\RunfileRepository;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Core\BinderInterface;
use Spiral\Logger\LogsInterface;

/**
 * Bootloader пакета runtime-диагностики ("первого реагирования").
 *
 * Responsibilities:
 * - задаёт defaults для секции конфигурации `diagnostics`;
 * - регистрирует консольные команды `diag:*`;
 * - регистрирует основные зависимости рантайма (runfile repository, interceptor).
 *
 * Это основной вход пакета со стороны Spiral-приложения.
 */
final class DiagnosticsBootloader extends Bootloader
{
    /**
     * @param ConfiguratorInterface $config Конфигуратор Spiral для задания defaults конфигурации.
     */
    public function __construct(private readonly ConfiguratorInterface $config) {}

    /**
     * Инициализация bootloader-а.
     *
     * @param ConsoleBootloader $console Bootloader консоли Spiral (регистрация команд).
     *
     * @throws \Spiral\Config\Exception\ConfigDeliveredException
     * @throws \Spiral\Core\Exception\ConfiguratorException
     */
    public function init(ConsoleBootloader $console): void
    {
        // Нужен, чтобы отсутствие config/diagnostics.php в хост-приложении не приводило к исключению.
        $this->config->setDefaults(
            section: DiagnosticsConfiguration::CONFIG,
            data: DiagnosticsConfiguration::getDefaults(),
        );

        $console->addCommand(ConfigCommand::class);
        $console->addCommand(InfoCommand::class);
        $console->addCommand(PublishCommand::class);
        $console->addCommand(StartCommand::class);
        $console->addCommand(StatusCommand::class);
        $console->addCommand(StopCommand::class);
    }

    /**
     * Регистрирует зависимости, которые нужны в рантайме.
     *
     * Важно: interceptor подключается в pipeline приложения отдельной настройкой (обычно `config/pipeline.php`).
     * Этот bootloader только обеспечивает, что зависимости interceptor-а и команд доступны в DI.
     *
     * @param BinderInterface $binder DI binder Spiral.
     * @param ContainerInterface $container DI container Spiral.
     */
    public function boot(BinderInterface $binder, ContainerInterface $container): void
    {
        /** @var DiagnosticsConfiguration $config */
        $config = $container->get(DiagnosticsConfiguration::class);

        // Единая точка доступа к runfile по пути из конфигурации.
        $binder->bindSingleton(
            alias: RunfileRepository::class,
            resolver: static fn(): RunfileRepository => new RunfileRepository(pathToFile: $config->getRunfilePath()),
        );

        // Interceptor зависит от:
        // - PSR-логгера (transport/handlers настраиваются в хост-приложении)
        // - invocation (read-only проверка активности диагностики)
        $binder->bindSingleton(
            alias: DiagnosticsInterceptor::class,
            resolver: static fn() => new DiagnosticsInterceptor(
                callableAttribute: $config->getCallableAttribute(),
                logger: $container->get(LogsInterface::class)->getLogger($config->getLogChannel()),
                invocation: new DiagnosticsInvocation($container->get(RunfileRepository::class)),
            ),
        );
    }
}
