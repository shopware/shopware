<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Price\Struct\PriceCollection;

class ProductBasicStruct extends ApiBasicStruct
{
    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

    /**
     * @var Price
     */
    protected $calculatedListingPrice;

    /**
     * @var PriceCollection
     */
    private $calculatedPrices;

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

    public function getCalculatedListingPrice(): Price
    {
        return $this->calculatedListingPrice;
    }

    public function setCalculatedListingPrice(Price $calculatedListingPrice): void
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
}
