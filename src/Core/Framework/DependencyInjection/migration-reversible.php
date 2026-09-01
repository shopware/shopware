<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Migration\Reversible\Command\CreateReversibleMigrationCommand;
use Shopware\Core\Framework\Migration\Reversible\Command\ReversibleMigrationCommand;
use Shopware\Core\Framework\Migration\Reversible\MigrationLock;
use Shopware\Core\Framework\Migration\Reversible\MigrationProvider;
use Shopware\Core\Framework\Migration\Reversible\MigrationRunner;
use Shopware\Core\Framework\Migration\Reversible\MigrationStateStore;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MigrationProvider::class);

    $services->set(MigrationStateStore::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(MigrationLock::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(MigrationRunner::class)
        ->public()
        ->args([
            service(MigrationProvider::class),
            service(MigrationStateStore::class),
            service(MigrationLock::class),
            service(Connection::class),
        ]);

    $services->set(ReversibleMigrationCommand::class)
        ->args([
            service(MigrationRunner::class),
            service(KernelPluginCollection::class),
        ])
        ->tag('console.command');

    $services->set(CreateReversibleMigrationCommand::class)
        ->args([
            service(KernelPluginCollection::class),
            service(Filesystem::class),
            service(ClockInterface::class),
        ])
        ->tag('console.command');
};
