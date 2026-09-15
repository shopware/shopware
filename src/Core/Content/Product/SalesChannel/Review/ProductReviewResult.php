<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntitySearchResult<ProductReviewCollection>
 */
#[Package('after-sales')]
class ProductReviewResult extends EntitySearchResult
{
    #[BecomesReadonly(version: 'v6.8.0')]
    protected ?string $parentId = null;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected string $productId;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected RatingMatrix $matrix;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected ?ProductReviewEntity $customerReview = null;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected int $totalReviewsInCurrentLanguage;

    /**
     * Sets every property at once. From v6.8.0 on, the properties are readonly and cannot be set afterwards.
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setProductId(string $productId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->productId = $productId;
    }

    public function getMatrix(): RatingMatrix
    {
        return $this->matrix;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setMatrix(RatingMatrix $matrix): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->matrix = $matrix;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        return $this->customerReview;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setCustomerReview(?ProductReviewEntity $customerReview): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->customerReview = $customerReview;
    }

    public function getTotalReviewsInCurrentLanguage(): int
    {
        return $this->totalReviewsInCurrentLanguage;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setTotalReviewsInCurrentLanguage(int $totalReviewsInCurrentLanguage): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setParentId(?string $parentId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->parentId = $parentId;
    }
}
