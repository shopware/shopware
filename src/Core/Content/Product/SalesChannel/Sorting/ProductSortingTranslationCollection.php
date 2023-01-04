<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSortingTranslationEntity>
 */
#[Package('inventory')]
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
