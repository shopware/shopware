<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                      add(ProductCrossSellingTranslationEntity $entity)
 * @method void                                      set(string $key, ProductCrossSellingTranslationEntity $entity)
 * @method ProductCrossSellingTranslationEntity[]    getIterator()
 * @method ProductCrossSellingTranslationEntity[]    getElements()
 * @method ProductCrossSellingTranslationEntity|null get(string $key)
 * @method ProductCrossSellingTranslationEntity|null first()
 * @method ProductCrossSellingTranslationEntity|null last()
 */
class ProductCrossSellingTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductCrossSellingTranslationEntity::class;
    }
}
