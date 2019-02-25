<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(ProductStreamEntity $entity)
 * @method void                     set(string $key, ProductStreamEntity $entity)
 * @method ProductStreamEntity[]    getIterator()
 * @method ProductStreamEntity[]    getElements()
 * @method ProductStreamEntity|null get(string $key)
 * @method ProductStreamEntity|null first()
 * @method ProductStreamEntity|null last()
 */
class ProductStreamCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
