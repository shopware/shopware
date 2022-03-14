<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductReviewLoaderResult extends EntitySearchResult
{
    /**
     * @deprecated tag:v6.6.0 - Will be strictly declared as type: string
     *
     * @var string
     */
    protected $productId;

    /**
     * @deprecated tag:v6.6.0 - Will be strictly declared as type: ?string
     *
     * @var string|null
     */
    protected $parentId;

    /**
     * @deprecated tag:v6.6.0 - Will be strictly declared as type: RatingMatrix
     *
     * @var RatingMatrix
     */
    protected $matrix;

    /**
     * @deprecated tag:v6.6.0 - Will be strictly declared as type: ?ProductReviewEntity
     *
     * @var ProductReviewEntity|null
     */
    protected $customerReview;

    /**
     * @deprecated tag:v6.6.0 - Will be strictly declared as type: int
     *
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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
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
}
