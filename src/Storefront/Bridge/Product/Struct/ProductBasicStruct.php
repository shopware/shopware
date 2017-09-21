<?php

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ProductBasicStruct extends ApiBasicStruct
{
    /**
     * @var ProductDetailPriceBasicCollection
     */
    protected $prices;

    public function getPrice(int $quantity): ?ProductDetailPriceBasicStruct
    {
        /** @var ProductDetailPriceBasicStruct $price */
        foreach ($this->prices as $price) {
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

    public function getPrices(): ProductDetailPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductDetailPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}