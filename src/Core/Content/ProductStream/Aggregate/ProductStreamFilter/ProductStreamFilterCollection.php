<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(ProductStreamFilterEntity $entity)
 * @method void                           set(string $key, ProductStreamFilterEntity $entity)
 * @method ProductStreamFilterEntity[]    getIterator()
 * @method ProductStreamFilterEntity[]    getElements()
 * @method ProductStreamFilterEntity|null get(string $key)
 * @method ProductStreamFilterEntity|null first()
 * @method ProductStreamFilterEntity|null last()
 */
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
