<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Unit;

use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractMeasurementUnitProvider
{
    abstract public function getDecorated(): AbstractMeasurementUnitProvider;

    abstract public function getUnitInfo(string $unit): MeasurementDisplayUnitEntity;
}
