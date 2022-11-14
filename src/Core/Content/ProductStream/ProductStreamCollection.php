<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package business-ops
 * @extends EntityCollection<ProductStreamEntity>
 */
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
