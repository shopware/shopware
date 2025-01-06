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
 * @deprecated tag:v6.7.0 - Will be removed. Use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult instead
 *
 * @template-extends StorefrontSearchResult<ProductReviewCollection>
 */
#[Package('storefront')]
class ReviewLoaderResult extends StorefrontSearchResult
{
    /**
     * @deprecated tag:v6.7.0
     *
     * @var string|null
     */
    protected $parentId;

    /**
     * @deprecated tag:v6.7.0
     *
     * @var string
     */
    protected $productId;

    /**
     * @deprecated tag:v6.7.0
     *
     * @var StorefrontSearchResult<ProductReviewCollection>
     */
    protected $reviews;

    protected RatingMatrix $matrix;

    /**
     * @deprecated tag:v6.7.0
     *
     * @var ProductReviewEntity|null
     */
    protected $customerReview;

    /**
     * @deprecated tag:v6.7.0
     *
     * @var int
     */
    protected $totalReviews;

    protected int $totalReviewsInCurrentLanguage;

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

    public function getTotalReviewsInCurrentLanguage(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        return $this->totalReviewsInCurrentLanguage;
    }

    public function setTotalReviewsInCurrentLanguage(int $totalReviewsInCurrentLanguage): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', ProductReviewResult::class));

        $this->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
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
