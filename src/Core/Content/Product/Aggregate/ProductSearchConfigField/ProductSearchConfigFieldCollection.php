<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchConfigFieldEntity>
 */
#[Package('inventory')]
class ProductSearchConfigFieldCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_config_field_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchConfigFieldEntity::class;
    }
}
