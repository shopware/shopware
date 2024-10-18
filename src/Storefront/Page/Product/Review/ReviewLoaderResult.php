<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

/**
 * @deprecated tag:v6.7.0 - Use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult instead
 *
 * @template-extends StorefrontSearchResult<ProductReviewCollection>
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
     * @var StorefrontSearchResult<ProductReviewCollection>
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

    /**
     * @var int
     */
    protected $totalNativeReviews;

    public function getProductId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->productId = $productId;
    }

    /**
     * @return StorefrontSearchResult<ProductReviewCollection>
     */
    public function getReviews(): StorefrontSearchResult
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->reviews;
    }

    public function getMatrix(): RatingMatrix
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->matrix;
    }

    public function setMatrix(RatingMatrix $matrix): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->matrix = $matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->customerReview;
    }

    public function setCustomerReview(?ProductReviewEntity $customerReview): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->customerReview = $customerReview;
    }

    public function getTotalReviews(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->totalReviews = $totalReviews;
    }

    public function getTotalNativeReviews(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->totalNativeReviews;
    }

    public function setTotalNativeReviews(int $totalNativeReviews): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->totalNativeReviews = $totalNativeReviews;
    }

    public function getParentId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->parentId = $parentId;
    }
}
