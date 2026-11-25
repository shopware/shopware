<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\SystemCheck\Api\SystemCheckController;
use Shopware\Core\Framework\SystemCheck\Command\SystemCheckCommand;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecksHandler;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SystemCheckCommand::class)
        ->args([
            service(SystemChecker::class),
        ])
        ->tag('console.command');

    $services->set(SystemCheckResultStorage::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(SystemChecker::class)
        ->args([
            tagged_iterator('shopware.system_check'),
            service(SystemCheckResultStorage::class),
            service('messenger.default_bus'),
        ]);

    $services->set(SystemCheckController::class)
        ->public()
        ->args([
            tagged_iterator('shopware.system_check'),
            service(SystemCheckResultStorage::class),
            service(SystemChecker::class),
        ]);

    $services->set(RunSystemChecksHandler::class)
        ->args([
            service(SystemChecker::class),
        ])
        ->tag('messenger.message_handler');
};
