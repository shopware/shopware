<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(IntegrationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(IntegrationRoleDefinition::class)
        ->tag('shopware.entity.definition');
};
