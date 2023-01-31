<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductCrossSellingTranslationEntity>
 */
#[Package('inventory')]
class ProductCrossSellingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_cross_selling_assigned_products_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductCrossSellingTranslationEntity::class;
    }
}
