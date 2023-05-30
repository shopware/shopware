<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SalesChannelProductCollection extends ProductCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelProductEntity::class;
    }
}
