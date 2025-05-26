<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\ProductMeasurement;

use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\ConvertedUnitSet;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\AbstractMeasurementUnitConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
class ProductMeasurementUnitBuilder
{
    public function __construct(
        private readonly AbstractMeasurementUnitConverter $unitConverter
    ) {
    }

    public function build(Entity $product, SalesChannelContext $context): ConvertedUnitSet
    {
        $measurementUnit = new ConvertedUnitSet();

        $lengthUnit = $context->getMeasurementSystem()->getUnit('length');
        $weightUnit = $context->getMeasurementSystem()->getUnit('weight');

        if (\is_float($product->get('width'))) {
            $measurementUnit->add('width', $this->unitConverter->convert($product->get('width'), MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($product->get('height'))) {
            $measurementUnit->add('height', $this->unitConverter->convert($product->get('height'), MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($product->get('length'))) {
            $measurementUnit->add('length', $this->unitConverter->convert($product->get('length'), MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($product->get('weight'))) {
            $measurementUnit->add('weight', $this->unitConverter->convert($product->get('weight'), MeasurementUnits::DEFAULT_WEIGHT_UNIT, $weightUnit));
        }

        return $measurementUnit;
    }
}
