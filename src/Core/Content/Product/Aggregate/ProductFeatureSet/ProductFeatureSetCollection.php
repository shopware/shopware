<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(ProductFeatureSetEntity $entity)
 * @method void                         set(string $key, ProductFeatureSetEntity $entity)
 * @method ProductFeatureSetEntity[]    getIterator()
 * @method ProductFeatureSetEntity[]    getElements()
 * @method ProductFeatureSetEntity|null get(string $key)
 * @method ProductFeatureSetEntity|null first()
 * @method ProductFeatureSetEntity|null last()
 */
class ProductFeatureSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductFeatureSetEntity::class;
    }
}
