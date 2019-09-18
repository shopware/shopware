<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\Review\RatingMatrix;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;

class ProductPage extends Page
{
    /**
     * @var SalesChannelProductEntity
     */
    protected $product;

    /**
     * @var CmsPageEntity
     */
    protected $cmsPage;

    /**
     * @var PropertyGroupCollection
     */
    protected $configuratorSettings;

    /**
     * @var StorefrontSearchResult
     */
    protected $reviews;

    /**
     * @var ProductReviewEntity|null
     */
    private $customerReview;

    /**
     * @var RatingMatrix
     */
    private $ratingMatrix;

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->product;
    }

    public function setProduct(SalesChannelProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getConfiguratorSettings(): PropertyGroupCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(PropertyGroupCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function setReviewLoaderResult(ReviewLoaderResult $result): void
    {
        $this->setReviews($result->getReviews());
        $this->setRatingMatrix($result->getMatrix());
        $this->setCustomerReview($result->getCustomerReview());
    }

    public function getReviews(): StorefrontSearchResult
    {
        return $this->reviews;
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        return $this->customerReview;
    }

    public function getRatingMatrix(): RatingMatrix
    {
        return $this->ratingMatrix;
    }

    private function setReviews(StorefrontSearchResult $reviews): void
    {
        $this->reviews = $reviews;
    }

    private function setCustomerReview(?ProductReviewEntity $customerReview): ProductPage
    {
        $this->customerReview = $customerReview;

        return $this;
    }

    private function setRatingMatrix(RatingMatrix $ratingMatrix): ProductPage
    {
        $this->ratingMatrix = $ratingMatrix;

        return $this;
    }
}
