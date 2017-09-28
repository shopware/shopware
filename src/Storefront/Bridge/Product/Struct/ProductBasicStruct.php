<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ProductBasicStruct extends ApiBasicStruct
{
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
