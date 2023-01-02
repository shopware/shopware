<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix as CoreRatingMatrix;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

/**
 * @package storefront
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

    /**
     * @deprecated tag:v6.5.0 - Will only accept the `\Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix`
     *
     * @var RatingMatrix|CoreRatingMatrix
     */
    protected $matrix;

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
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getReviews(): StorefrontSearchResult
    {
        return $this->reviews;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - will return `\Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix` in the future
     */
    public function getMatrix(): RatingMatrix
    {
        return $this->matrix;
    }

    /**
     * @deprecated tag:v6.5.0 - will only accept `\Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix` in the future
     */
    public function setMatrix(RatingMatrix $matrix): void
    {
        if (!$matrix instanceof CoreRatingMatrix) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Method `setMatrix()` of class `ReviewLoaderResult` will only accept "Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix" as input param in v6.5.0.0'
            );
        }

        $this->matrix = $matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        return $this->customerReview;
    }

    public function setCustomerReview(?ProductReviewEntity $customerReview): void
    {
        $this->customerReview = $customerReview;
    }

    public function getTotalReviews(): int
    {
        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): void
    {
        $this->totalReviews = $totalReviews;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }
}
