<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Increment\ArrayIncrementer;
use Shopware\Core\Framework\Increment\Controller\IncrementApiController;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('shopware.increment.gateway.registry', IncrementGatewayRegistry::class)
        ->public()
        ->args([
            tagged_iterator('shopware.increment.gateway'),
        ]);

    $services->set('shopware.increment.gateway.mysql', MySQLIncrementer::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set('shopware.increment.gateway.array', ArrayIncrementer::class)
        ->tag('kernel.reset', ['method' => 'resetAll']);

    $services->set(IncrementApiController::class)
        ->public()
        ->args([
            service('shopware.increment.gateway.registry'),
        ]);
};
