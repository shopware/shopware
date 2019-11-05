<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
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
     * @var ReviewLoaderResult
     */
    protected $reviewLoaderResult;

    /**
     * @var PropertyGroupOptionCollection
     */
    protected $selectedOptions;

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

    public function getReviewLoaderResult(): ReviewLoaderResult
    {
        return $this->reviewLoaderResult;
    }

    public function setReviewLoaderResult(ReviewLoaderResult $result): void
    {
        $this->reviewLoaderResult = $result;
    }

    public function getReviews(): StorefrontSearchResult
    {
        return $this->reviewLoaderResult->getReviews();
    }

    public function getCustomerReview(): ?ProductReviewEntity
    {
        return $this->reviewLoaderResult->getCustomerReview();
    }

    public function getRatingMatrix(): RatingMatrix
    {
        return $this->reviewLoaderResult->getMatrix();
    }

    public function getTotalReviews(): int
    {
        return $this->reviewLoaderResult->getTotalReviews();
    }

    public function getSelectedOptions(): PropertyGroupOptionCollection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(PropertyGroupOptionCollection $selectedOptions): void
    {
        $this->selectedOptions = $selectedOptions;
    }
}
