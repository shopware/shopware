<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

/**
 * @deprecated tag:v6.6.0 use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead
 */
#[Package('storefront')]
class ReviewLoaderResult extends StorefrontSearchResult
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var StorefrontSearchResult
     */
    protected $reviews;

    protected RatingMatrix $matrix;

    /**
     * @var ProductReviewEntity|null
     */
    protected $customerReview;

    /**
     * @var int
     */
    protected $totalReviews;

    public function getProductId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        $this->productId = $productId;
    }

    public function getReviews(): StorefrontSearchResult
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->reviews;
    }

    public function getMatrix(): RatingMatrix
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->matrix;
    }

    public function setMatrix(RatingMatrix $matrix): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        $this->matrix = $matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->customerReview;
    }

    public function setCustomerReview(?ProductReviewEntity $customerReview): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        $this->customerReview = $customerReview;
    }

    public function getTotalReviews(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        $this->totalReviews = $totalReviews;
    }

    public function getParentId(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ReviewLoaderResult will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult instead'
        );

        $this->parentId = $parentId;
    }
}
