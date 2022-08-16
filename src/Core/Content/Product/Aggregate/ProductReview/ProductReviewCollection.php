<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductReview;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductReviewEntity>
 */
class ProductReviewCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_review_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductReviewEntity::class;
    }
}
