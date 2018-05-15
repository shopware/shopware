<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaBasicCollection;
use Shopware\Content\Product\Aggregate\ProductMedia\Struct\ProductMediaBasicStruct;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPriceCollection;

trait StorefrontProductBasicTrait
{
    /**
     * @var CalculatedPrice
     */
    protected $calculatedListingPrice;

    /**
     * @var CalculatedPriceCollection
     */
    protected $calculatedContextPrices;

    /**
     * @var CalculatedPrice
     */
    protected $calculatedPrice;

    public function isAvailable(): bool
    {
        if (!$this->getIsCloseout()) {
            return true;
        }

        return $this->getStock() >= $this->getMinPurchase();
    }

    public function getCalculatedListingPrice(): CalculatedPrice
    {
        return $this->calculatedListingPrice;
    }

    public function setCalculatedListingPrice(CalculatedPrice $calculatedListingPrice): void
    {
        $this->calculatedListingPrice = $calculatedListingPrice;
    }

    public function setCalculatedContextPrices(CalculatedPriceCollection $prices): void
    {
        $this->calculatedContextPrices = $prices;
    }

    public function getCalculatedContextPrices(): CalculatedPriceCollection
    {
        return $this->calculatedContextPrices;
    }

    public function getCalculatedPrice(): CalculatedPrice
    {
        return $this->calculatedPrice;
    }

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void
    {
        $this->calculatedPrice = $calculatedPrice;
    }
}
