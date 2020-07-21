<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                    add(ProductFeatureSetTranslationEntity $entity)
 * @method void                                    set(string $key, ProductFeatureSetTranslationEntity $entity)
 * @method ProductFeatureSetTranslationEntity[]    getIterator()
 * @method ProductFeatureSetTranslationEntity[]    getElements()
 * @method ProductFeatureSetTranslationEntity|null get(string $key)
 * @method ProductFeatureSetTranslationEntity|null first()
 * @method ProductFeatureSetTranslationEntity|null last()
 */
class ProductFeatureSetTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductFeatureSetTranslationEntity::class;
    }
}
