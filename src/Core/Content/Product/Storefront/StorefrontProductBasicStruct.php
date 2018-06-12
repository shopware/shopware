<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Core\Content\Product\ProductBasicStruct as ApiBasicStruct;

class StorefrontProductBasicStruct extends ApiBasicStruct
{
    /**
     * @var CalculatedPrice
     */
    protected $calculatedListingPrice;

    /**
     * @var CalculatedPriceCollection
     */
    protected $calculatedPriceRules;

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

    public function setCalculatedPriceRules(CalculatedPriceCollection $prices): void
    {
        $this->calculatedPriceRules = $prices;
    }

    public function getCalculatedPriceRules(): CalculatedPriceCollection
    {
        return $this->calculatedPriceRules;
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
