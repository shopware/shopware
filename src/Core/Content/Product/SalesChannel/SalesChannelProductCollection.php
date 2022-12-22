<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\ProductCollection;

/**
 * @package inventory
 */
class SalesChannelProductCollection extends ProductCollection
{
    public function getExpectedClass(): string
    {
        return SalesChannelProductEntity::class;
    }
}
