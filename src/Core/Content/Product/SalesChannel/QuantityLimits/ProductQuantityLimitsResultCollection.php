<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ProductQuantityLimitsResult>
 */
#[Package('inventory')]
class ProductQuantityLimitsResultCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ProductQuantityLimitsResult::class;
    }
}
