<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 */
#[CoversClass(ProductReviewResult::class)]
class ProductReviewResultTest extends TestCase
{
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $result = ProductReviewResult::fromSearchResult(
            $source,
            matrix: new RatingMatrix([]),
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 5,
        );

        static::assertSame($source->getTotal(), $result->getTotal());
        static::assertSame($source->getEntities(), $result->getEntities());
        static::assertSame($source->getCriteria(), $result->getCriteria());
        static::assertSame($source->getContext(), $result->getContext());
    }

    public function testFromSearchResultSetsReviewSpecificFields(): void
    {
        $matrix = new RatingMatrix([]);
        $customerReview = new ProductReviewEntity();

        $result = ProductReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: $matrix,
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 7,
            customerReview: $customerReview,
            parentId: 'parent-1',
        );

        static::assertSame($matrix, $result->getMatrix());
        static::assertSame('product-1', $result->getProductId());
        static::assertSame(7, $result->getTotalReviewsInCurrentLanguage());
        static::assertSame($customerReview, $result->getCustomerReview());
        static::assertSame('parent-1', $result->getParentId());
    }

    public function testFromSearchResultUsesDefaultsForOptionalExtras(): void
    {
        $result = ProductReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: new RatingMatrix([]),
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 0,
        );

        static::assertNull($result->getCustomerReview());
        static::assertNull($result->getParentId());
    }

    /**
     * @return EntitySearchResult<ProductReviewCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new ProductReviewCollection();

        return new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
