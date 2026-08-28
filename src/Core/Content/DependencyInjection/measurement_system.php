<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Shopware\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\Unit\MeasurementUnitProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MeasurementSystemEntity::class)
        ->tag('shopware.entity');

    $services->set(MeasurementDisplayUnitEntity::class)
        ->tag('shopware.entity');

    $services->set(MeasurementUnitsFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(MeasurementUnitProvider::class)
        ->args([
            service('measurement_display_unit.repository'),
        ]);

    $services->set(MeasurementUnitConverter::class)
        ->args([
            service(MeasurementUnitProvider::class),
        ]);

    $services->set(ProductMeasurementUnitBuilder::class)
        ->args([
            service(MeasurementUnitConverter::class),
        ]);

    $services->set(MeasurementConvertUnitTwigFilter::class)
        ->args([
            service(MeasurementUnitProvider::class),
            service(MeasurementUnitConverter::class),
        ])
        ->tag('twig.extension');
};
