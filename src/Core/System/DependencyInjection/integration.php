<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\Integration\Subscriber\AdminIntegrationWriteValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(IntegrationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(IntegrationRoleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AdminIntegrationWriteValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');
};
