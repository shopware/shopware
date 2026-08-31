<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\Api\CustomFieldSetActionController;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomFieldDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetRelationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetActionController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(CustomFieldService::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CustomFieldSetPersister::class)
        ->args([
            service('custom_field_set.repository'),
            service(Connection::class),
            service('custom_field_set_relation.repository'),
            service('custom_field.repository'),
        ]);
};
