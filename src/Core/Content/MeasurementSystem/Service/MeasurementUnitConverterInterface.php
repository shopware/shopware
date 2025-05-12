<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Service;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface MeasurementUnitConverterInterface
{
    public function convert(float $value, string $fromUnit, string $toUnit): ConvertedUnit;
}
