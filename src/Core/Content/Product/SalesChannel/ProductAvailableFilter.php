<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('inventory')]
class ProductAvailableFilter extends MultiFilter
{
    public function __construct(
        string $salesChannelId,
        int $visibility = ProductVisibilityDefinition::VISIBILITY_ALL
    ) {
        parent::__construct(
            self::CONNECTION_AND,
            [
                new RangeFilter('product.visibilities.visibility', [RangeFilter::GTE => $visibility]),
                new EqualsFilter('product.visibilities.salesChannelId', $salesChannelId),
                new EqualsFilter('product.active', true),
            ]
        );
    }
}
