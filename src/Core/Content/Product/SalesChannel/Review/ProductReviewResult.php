<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntitySearchResult<ProductReviewCollection>
 */
#[Package('after-sales')]
class ProductReviewResult extends EntitySearchResult
{
    protected ?string $parentId = null;

    protected string $productId;

    protected RatingMatrix $matrix;

    protected ?ProductReviewEntity $customerReview = null;

    protected int $totalReviewsInCurrentLanguage;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
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

    public function getTotalReviewsInCurrentLanguage(): int
    {
        return $this->totalReviewsInCurrentLanguage;
    }

    public function setTotalReviewsInCurrentLanguage(int $totalReviewsInCurrentLanguage): void
    {
        $this->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
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
