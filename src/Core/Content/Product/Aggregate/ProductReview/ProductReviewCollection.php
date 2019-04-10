<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductReview;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(ProductReviewEntity $entity)
 * @method void                     set(string $key, ProductReviewEntity $entity)
 * @method ProductReviewEntity[]    getIterator()
 * @method ProductReviewEntity[]    getElements()
 * @method ProductReviewEntity|null get(string $key)
 * @method ProductReviewEntity|null first()
 * @method ProductReviewEntity|null last()
 */
class ProductReviewCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductReviewEntity::class;
    }
}
