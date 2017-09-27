<?php

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ProductDetailStruct extends \Shopware\Product\Struct\ProductDetailStruct
{
    public function getPrice(int $quantity): ?ProductDetailPriceBasicStruct
    {
        /** @var ProductDetailPriceBasicStruct $price */
        foreach ($this->mainDetail->getPrices() as $price) {
            if ($price->getQuantityStart() > $quantity) {
                continue;
            }
            if (null !== $price->getQuantityEnd() && $price->getQuantityEnd() < $quantity) {
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
