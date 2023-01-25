<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamEntity>
 */
#[Package('business-ops')]
class ProductStreamCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_stream_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
