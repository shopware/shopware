<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CreateFromTrait;

/**
 * @deprecated tag:v6.8.0 reason:class-hierarchy-change - Will no longer extend EntitySearchResult.
 *
 * @extends EntitySearchResult<ProductReviewCollection>
 */
#[Package('after-sales')]
class ProductReviewResult extends EntitySearchResult
{
    use CreateFromTrait;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected ?string $parentId = null;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected string $productId;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected RatingMatrix $matrix;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected ?ProductReviewEntity $customerReview = null;

    /**
     * @deprecated tag:v6.8.0 - Will become readonly in v6.8.0.
     */
    protected int $totalReviewsInCurrentLanguage;

    /**
     * Construction entry point with a stable signature across the v6.8.0 cut. Callers that adopt this method now will keep working after the structural change.
     *
     * @param EntitySearchResult<ProductReviewCollection> $result
     */
    public static function fromSearchResult(
        EntitySearchResult $result,
        RatingMatrix $matrix,
        string $productId,
        int $totalReviewsInCurrentLanguage,
        ?ProductReviewEntity $customerReview = null,
        ?string $parentId = null,
    ): self {
        $instance = self::createFrom($result);
        $instance->matrix = $matrix;
        $instance->productId = $productId;
        $instance->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
        $instance->customerReview = $customerReview;
        $instance->parentId = $parentId;

        return $instance;
    }

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
