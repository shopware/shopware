<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

if (Feature::isActive('v6.7.0.0')) {
    /**
     * @template-extends EntitySearchResult<ProductReviewCollection>
     */
    #[Package('storefront')]
    class ReviewLoaderResult extends EntitySearchResult
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
         * @var EntitySearchResult<ProductReviewCollection>
         */
        protected EntitySearchResult $reviews;

        protected RatingMatrix $matrix;

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

        /**
         * @return EntitySearchResult<ProductReviewCollection>
         */
        public function getReviews(): EntitySearchResult
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
} else {
    /**
     * @deprecated tag:v6.7.0 - Will inherit from EntitySearchResult<ProductReviewCollection>
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

        public function getProductId(): string
        {
            return $this->productId;
        }

        public function setProductId(string $productId): void
        {
            $this->productId = $productId;
        }

        /**
         * @deprecated tag:v6.7.0 - Return type will change to EntitySearchResult<ProductReviewCollection>
         *
         * @return StorefrontSearchResult<ProductReviewCollection>
         */
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
}
