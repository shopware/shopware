<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CalculatedListingPrice;
use Shopware\Core\Framework\Feature;

class SalesChannelProductEntity extends ProductEntity
{
    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10553) tag:v6.4.0 - Will be removed, use $calculatedCheapestPrice instead
     *
     * @var CalculatedListingPrice
     */
    protected $calculatedListingPrice;

    /**
     * @var PriceCollection
     */
    protected $calculatedPrices;

    /**
     * @var CalculatedPrice
     */
    protected $calculatedPrice;

    /**
     * @var PropertyGroupCollection|null
     */
    protected $sortedProperties;

    /**
     * @internal (flag:FEATURE_NEXT_10553) - remove nullable flag
     *
     * @var CalculatedCheapestPrice|null
     */
    protected $calculatedCheapestPrice;

    /**
     * @var bool
     */
    protected $isNew = false;

    /**
     * @var int
     */
    protected $calculatedMaxPurchase;

    /**
     * @var CategoryEntity|null
     */
    protected $seoCategory;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10553) tag:v6.4.0 - Will be removed, use $calculatedCheapestPrice instead
     */
    public function getCalculatedListingPrice(): ?CalculatedListingPrice
    {
        return $this->calculatedListingPrice;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10553) tag:v6.4.0 - Will be removed, use $calculatedCheapestPrice instead
     */
    public function setCalculatedListingPrice(CalculatedListingPrice $calculatedListingPrice): void
    {
        $this->calculatedListingPrice = $calculatedListingPrice;
    }

    public function setCalculatedPrices(PriceCollection $prices): void
    {
        $this->calculatedPrices = $prices;
    }

    public function getCalculatedPrices(): PriceCollection
    {
        return $this->calculatedPrices;
    }

    public function getCalculatedPrice(): CalculatedPrice
    {
        return $this->calculatedPrice;
    }

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void
    {
        $this->calculatedPrice = $calculatedPrice;
    }

    public function getSortedProperties(): ?PropertyGroupCollection
    {
        return $this->sortedProperties;
    }

    public function setSortedProperties(?PropertyGroupCollection $sortedProperties): void
    {
        $this->sortedProperties = $sortedProperties;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_10553) tag:v6.4.0 - Will be removed, use $calculatedCheapestPrice.hasRange instead
     */
    public function hasPriceRange(): bool
    {
        if (Feature::isActive('FEATURE_NEXT_10553')) {
            if ($this->getCalculatedCheapestPrice() !== null) {
                return $this->getCalculatedCheapestPrice()->hasRange();
            }

            return $this->getCalculatedPrices()->count() > 1;
        }

        if ($this->getCalculatedListingPrice() !== null) {
            return $this->getCalculatedListingPrice()->hasRange() || $this->getCalculatedPrices()->count() > 1;
        }

        return $this->getCalculatedPrices()->count() > 1;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }

    public function getCalculatedMaxPurchase(): int
    {
        return $this->calculatedMaxPurchase;
    }

    public function setCalculatedMaxPurchase(int $calculatedMaxPurchase): void
    {
        $this->calculatedMaxPurchase = $calculatedMaxPurchase;
    }

    public function getSeoCategory(): ?CategoryEntity
    {
        return $this->seoCategory;
    }

    public function setSeoCategory(?CategoryEntity $category): void
    {
        $this->seoCategory = $category;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10553)
     */
    public function getCalculatedCheapestPrice(): ?CalculatedCheapestPrice
    {
        return $this->calculatedCheapestPrice;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10553)
     */
    public function setCalculatedCheapestPrice(?CalculatedCheapestPrice $calculatedCheapestPrice): void
    {
        $this->calculatedCheapestPrice = $calculatedCheapestPrice;
    }
}
