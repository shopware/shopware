<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will no longer extend EntitySearchResult.
 *
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
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->productId = $productId;
    }

    public function getMatrix(): RatingMatrix
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->matrix;
    }

    public function setMatrix(RatingMatrix $matrix): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->matrix = $matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->customerReview;
    }

    public function setCustomerReview(?ProductReviewEntity $customerReview): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->customerReview = $customerReview;
    }

    public function getTotalReviewsInCurrentLanguage(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->totalReviewsInCurrentLanguage;
    }

    public function setTotalReviewsInCurrentLanguage(int $totalReviewsInCurrentLanguage): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
    }

    public function getParentId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->parentId = $parentId;
    }
}
