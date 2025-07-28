<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\ProductMeasurement;

use Shopware\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
enum ProductMeasurementEnum: string
{
    case WIDTH = 'width';
    case HEIGHT = 'height';
    case LENGTH = 'length';
    case WEIGHT = 'weight';
    public const DIMENSIONS_MAPPING = [
        self::WIDTH->value => MeasurementUnitTypeEnum::LENGTH,
        self::HEIGHT->value => MeasurementUnitTypeEnum::LENGTH,
        self::LENGTH->value => MeasurementUnitTypeEnum::LENGTH,
        self::WEIGHT->value => MeasurementUnitTypeEnum::WEIGHT,
    ];
}
