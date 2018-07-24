<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductStruct as ApiStruct;

class StorefrontProductStruct extends ApiStruct
{
    /**
     * @var Price
     */
    protected $calculatedListingPrice;

    /**
     * @var PriceCollection
     */
    protected $calculatedPriceRules;

    /**
     * @var Price
     */
    protected $calculatedPrice;

    public function isAvailable(): bool
    {
        if (!$this->getIsCloseout()) {
            return true;
        }

        return $this->getStock() >= $this->getMinPurchase();
    }

    public function getCalculatedListingPrice(): Price
    {
        return $this->calculatedListingPrice;
    }

    public function setCalculatedListingPrice(Price $calculatedListingPrice): void
    {
        $this->calculatedListingPrice = $calculatedListingPrice;
    }

    public function setCalculatedPriceRules(PriceCollection $prices): void
    {
        $this->calculatedPriceRules = $prices;
    }

    public function getCalculatedPriceRules(): PriceCollection
    {
        return $this->calculatedPriceRules;
    }

    public function getCalculatedPrice(): Price
    {
        return $this->calculatedPrice;
    }

    public function setCalculatedPrice(Price $calculatedPrice): void
    {
        $this->calculatedPrice = $calculatedPrice;
    }
}
