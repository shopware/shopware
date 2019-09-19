<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class ReviewLoaderResult extends Struct
{
    /**
     * @var StorefrontSearchResult
     */
    private $reviews;

    /**
     * @var RatingMatrix
     */
    private $matrix;

    /**
     * @var ProductReviewEntity|null
     */
    private $customerReview;

    /**
     * @var int
     */
    private $totalReviews;

    public function __construct(StorefrontSearchResult $storefrontSearchResult, RatingMatrix $matrix, ?ProductReviewEntity $customerReview, int $totalReviews)
    {
        $this->reviews = $storefrontSearchResult;
        $this->matrix = $matrix;
        $this->customerReview = $customerReview;
        $this->totalReviews = $totalReviews;
    }

    public function getReviews(): StorefrontSearchResult
    {
        return $this->reviews;
    }

    public function getMatrix(): RatingMatrix
    {
        return $this->matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        return $this->customerReview;
    }

    public function getTotalReviews(): int
    {
        return $this->totalReviews;
    }
}
