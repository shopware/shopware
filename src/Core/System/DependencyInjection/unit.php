<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\System\Unit\UnitUneceCodes;
use Shopware\Core\System\Unit\UnitValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UnitDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UnitTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UnitUneceCodes::class);

    $services->set(UnitValidator::class)
        ->args([service(UnitUneceCodes::class)])
        ->tag('kernel.event_subscriber');
};
