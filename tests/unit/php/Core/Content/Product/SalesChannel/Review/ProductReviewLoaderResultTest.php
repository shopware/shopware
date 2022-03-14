<?php declare(strict_types=1);

namespace Tests\Unit\Shopware\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult
 */
final class ProductReviewLoaderResultTest extends TestCase
{
    public function testProductId(): void
    {
        $productReviewLoaderResult = $this->getProductReviewLoaderResult();

        $productId = Uuid::randomHex();
        $productReviewLoaderResult->setProductId($productId);

        static::assertSame($productId, $productReviewLoaderResult->getProductId());
    }

    public function testParentId(): void
    {
        $productReviewLoaderResult = $this->getProductReviewLoaderResult();

        $parentId = Uuid::randomHex();
        $productReviewLoaderResult->setParentId($parentId);

        static::assertSame($parentId, $productReviewLoaderResult->getParentId());
    }

    public function testMatrix(): void
    {
        $productReviewLoaderResult = $this->getProductReviewLoaderResult();

        $matrix = new RatingMatrix([]);
        $productReviewLoaderResult->setMatrix($matrix);

        static::assertSame($matrix, $productReviewLoaderResult->getMatrix());
    }

    public function testCustomerReview(): void
    {
        $productReviewLoaderResult = $this->getProductReviewLoaderResult();

        $review = new ProductReviewEntity();
        $productReviewLoaderResult->setCustomerReview($review);

        static::assertSame($review, $productReviewLoaderResult->getCustomerReview());
    }

    public function testTotalReviews(): void
    {
        $productReviewLoaderResult = $this->getProductReviewLoaderResult();

        $totalReviews = 42;
        $productReviewLoaderResult->setTotalReviews($totalReviews);

        static::assertSame($totalReviews, $productReviewLoaderResult->getTotalReviews());
    }

    private function getProductReviewLoaderResult(): ProductReviewLoaderResult
    {
        return new ProductReviewLoaderResult(
            ProductReviewDefinition::ENTITY_NAME,
            42,
            new ProductReviewCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
