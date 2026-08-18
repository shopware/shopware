<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends ProductCollection<SalesChannelProductEntity>
 */
#[Package('inventory')]
class SalesChannelProductCollection extends ProductCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_product_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelProductEntity::class;
    }
}
