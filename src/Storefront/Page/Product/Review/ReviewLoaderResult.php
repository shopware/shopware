<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

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
     * @var RatingMatrix
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

    public function getMatrix(): RatingMatrix
    {
        return $this->matrix;
    }

    public function setMatrix(RatingMatrix $matrix): void
    {
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
