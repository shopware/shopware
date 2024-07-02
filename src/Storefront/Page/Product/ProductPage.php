<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;

#[Package('storefront')]
class ProductPage extends Page
{
    /**
     * @deprecated tag:v6.7.0 - Property will be native typed
     *
     * @var SalesChannelProductEntity
     */
    protected $product;

    /**
     * @deprecated tag:v6.7.0 - Property will be native typed
     *
     * @var CmsPageEntity
     */
    protected $cmsPage;

    protected ?string $navigationId = null;

    /**
     * @deprecated tag:v6.7.0 - Property will be native typed
     *
     * @var PropertyGroupCollection
     */
    protected $configuratorSettings;

    /**
     * @deprecated tag:v6.7.0 - Property will be removed as it is not used anymore
     *
     * @var ReviewLoaderResult|null
     */
    protected $reviewLoaderResult;

    /**
     * @deprecated tag:v6.7.0 - Property will be native typed
     *
     * @var PropertyGroupOptionCollection
     */
    protected $selectedOptions;

    /**
     * @deprecated tag:v6.7.0 - Property will be removed as it is not used anymore
     *
     * @var CrossSellingElementCollection|null
     */
    protected $crossSellings;

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

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getConfiguratorSettings(): PropertyGroupCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(PropertyGroupCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed as the property is not used anymore
     */
    public function getReviews(): ?ReviewLoaderResult
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );

        return $this->reviewLoaderResult;
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed as the property is not used anymore
     */
    public function setReviews(ReviewLoaderResult $result): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );
        $this->reviewLoaderResult = $result;
    }

    public function getSelectedOptions(): PropertyGroupOptionCollection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(PropertyGroupOptionCollection $selectedOptions): void
    {
        $this->selectedOptions = $selectedOptions;
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed as the property is not used anymore
     */
    public function getCrossSellings(): ?CrossSellingElementCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );

        return $this->crossSellings;
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed as the property is not used anymore
     */
    public function setCrossSellings(CrossSellingElementCollection $crossSellings): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );
        $this->crossSellings = $crossSellings;
    }

    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
