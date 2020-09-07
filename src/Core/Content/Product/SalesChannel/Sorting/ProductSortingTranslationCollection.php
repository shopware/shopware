<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                 add(ProductSortingTranslationEntity $entity)
 * @method void                                 set(string $key, ProductSortingTranslationEntity $entity)
 * @method ProductSortingTranslationEntity[]    getIterator()
 * @method ProductSortingTranslationEntity[]    getElements()
 * @method ProductSortingTranslationEntity|null get(string $key)
 * @method ProductSortingTranslationEntity|null first()
 * @method ProductSortingTranslationEntity|null last()
 */
class ProductSortingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_sorting_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSortingTranslationEntity::class;
    }
}
