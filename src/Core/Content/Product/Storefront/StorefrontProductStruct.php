<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductStruct as ApiStruct;

class StorefrontProductStruct extends ApiStruct
{
    /**
     * @var CalculatedPrice
     */
    protected $calculatedListingPrice;

    /**
     * @var PriceCollection
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

    public function setCalculatedPriceRules(PriceCollection $prices): void
    {
        $this->calculatedPriceRules = $prices;
    }

    public function getCalculatedPriceRules(): PriceCollection
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
