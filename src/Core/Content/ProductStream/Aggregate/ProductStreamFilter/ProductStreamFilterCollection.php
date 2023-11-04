<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamFilterEntity>
 */
#[Package('business-ops')]
class ProductStreamFilterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_stream_filter_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamFilterEntity::class;
    }
}
