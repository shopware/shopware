<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DeliveryTimeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DeliveryTimeTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
