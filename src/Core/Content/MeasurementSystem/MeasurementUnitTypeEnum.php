<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
enum MeasurementUnitTypeEnum: string
{
    case LENGTH = 'length';
    case WEIGHT = 'weight';
}
