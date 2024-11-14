<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SalesChannelProductEntity extends ProductEntity
{
    /**
     * @var PriceCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $calculatedPrices;

    /**
     * @var CalculatedPrice
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $calculatedPrice;

    /**
     * @var PropertyGroupCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $sortedProperties;

    /**
     * @var CalculatedCheapestPrice
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $calculatedCheapestPrice;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isNew = false;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $calculatedMaxPurchase;

    /**
     * @var CategoryEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $seoCategory;

    /**
     * The container will be resolved on product.loaded event and
     * the detected cheapest price will be set for the current context rules
     *
     * @var CheapestPrice|CheapestPriceContainer|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cheapestPrice;

    /**
     * @var CheapestPriceContainer|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cheapestPriceContainer;

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

    public function getCalculatedCheapestPrice(): CalculatedCheapestPrice
    {
        return $this->calculatedCheapestPrice;
    }

    public function setCalculatedCheapestPrice(CalculatedCheapestPrice $calculatedCheapestPrice): void
    {
        $this->calculatedCheapestPrice = $calculatedCheapestPrice;
    }

    public function getCheapestPrice(): CheapestPrice|CheapestPriceContainer|null
    {
        return $this->cheapestPrice;
    }

    public function setCheapestPrice(?CheapestPrice $cheapestPrice): void
    {
        $this->cheapestPrice = $cheapestPrice;
    }

    public function setCheapestPriceContainer(CheapestPriceContainer $container): void
    {
        $this->cheapestPriceContainer = $container;
    }

    public function getCheapestPriceContainer(): ?CheapestPriceContainer
    {
        return $this->cheapestPriceContainer;
    }
}
