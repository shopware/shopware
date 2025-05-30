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

        $width = $product->get('width');
        $height = $product->get('height');
        $length = $product->get('length');
        $weight = $product->get('weight');

        if (\is_float($width)) {
            $measurementUnit->addUnit('width', $this->unitConverter->convert($width, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($height)) {
            $measurementUnit->addUnit('height', $this->unitConverter->convert($height, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($length)) {
            $measurementUnit->addUnit('length', $this->unitConverter->convert($length, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnit));
        }

        if (\is_float($weight)) {
            $measurementUnit->addUnit('weight', $this->unitConverter->convert($weight, MeasurementUnits::DEFAULT_WEIGHT_UNIT, $weightUnit));
        }

        return $measurementUnit;
    }
}
