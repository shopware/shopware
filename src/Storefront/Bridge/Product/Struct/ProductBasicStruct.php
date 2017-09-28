<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ProductBasicStruct extends ApiBasicStruct
{
    /**
     * @var ProductMediaBasicCollection
     */
    protected $media;

    public function getCover(): ?ProductMediaBasicStruct
    {
        return $this->media->filter(
            function(ProductMediaBasicStruct $media) {
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

    public function displayFromPrice(): bool
    {
        return $this->getListingPrice()->getDisplayFromPrice();
    }

    public function getListingPrice(): ProductListingPriceBasicStruct
    {
        if ($this->listingPrices->count() > 0) {
            return $this->listingPrices->first();
        }
        $price = ProductListingPriceBasicStruct::createFrom(
            $this->mainDetail->getPrices()->last()
        );
        $price->setDisplayFromPrice(
            $this->mainDetail->getPrices()->count() > 1
        );
        return $price;
    }

    public function getPrice(int $quantity): ?ProductDetailPriceBasicStruct
    {
        /** @var ProductDetailPriceBasicStruct $price */
        foreach ($this->mainDetail->getPrices() as $price) {
            if ($price->getQuantityStart() > $quantity) {
                continue;
            }
            if ($price->getQuantityEnd() !== null && $price->getQuantityEnd() < $quantity) {
                continue;
            }

            return $price;
        }

        return null;
    }

    public function isAvailable(): bool
    {
        if (!$this->getIsCloseout()) {
            return true;
        }

        return $this->getMainDetail()->getStock() >= $this->getMainDetail()->getMinPurchase();
    }
}
