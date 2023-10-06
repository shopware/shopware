<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchConfigEntity>
 */
#[Package('inventory')]
class ProductSearchConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchConfigEntity::class;
    }
}
