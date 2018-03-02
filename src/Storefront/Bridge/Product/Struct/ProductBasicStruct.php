<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;

class ProductBasicStruct extends ApiBasicStruct
{
    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

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

    public function getCover(): ?ProductMediaBasicStruct
    {
        return $this->media->filter(
            function (ProductMediaBasicStruct $media) {
                return $media->getIsCover();
            }
        )->first();
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(ProductMediaBasicCollection $media): void
    {
        $this->media = $media;
    }

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
