<?php

namespace Shopware\Product\Struct;

use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

class StorefrontBasicProductStruct extends ProductBasicStruct
{
    /**
     * @var ProductPriceBasicCollection
     */
    protected $prices;

    public function getPrice(int $quantity): ?ProductPriceBasicStruct
    {
        /** @var ProductPriceBasicStruct $price */
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

    public function getPrices(): ProductPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}