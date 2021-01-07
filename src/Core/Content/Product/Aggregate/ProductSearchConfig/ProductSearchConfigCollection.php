<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_10552)
 *
 * @method void                           add(ProductSearchConfigEntity $entity)
 * @method void                           set(string $key, ProductSearchConfigEntity $entity)
 * @method ProductSearchConfigEntity[]    getIterator()
 * @method ProductSearchConfigEntity[]    getElements()
 * @method ProductSearchConfigEntity|null get(string $key)
 * @method ProductSearchConfigEntity|null first()
 * @method ProductSearchConfigEntity|null last()
 */
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
