<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UnitDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UnitTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
