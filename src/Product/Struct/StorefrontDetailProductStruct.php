<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\ProductPrice\Struct\ProductDetailPrice;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

class StorefrontDetailProductStruct extends ProductDetailStruct
{
    /**
     * @var ProductPriceBasicCollection
     */
    protected $prices;

    /**
     * @var ProductDetailPrice
     */
    protected $detailPrice;

    public function getPrices(): ProductPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }

    /**
     * @return ProductDetailPrice
     */
    public function getDetailPrice(): ProductDetailPrice
    {
        return $this->detailPrice;
    }

    /**
     * @param ProductDetailPrice $detailPrice
     */
    public function setDetailPrice(ProductDetailPrice $detailPrice)
    {
        $this->detailPrice = $detailPrice;
    }

    public function isAvailable(): bool
    {
        if ($this->getIsCloseout()) {
            return true;
        }

        $mainDetail = $this->getMainDetail();

        return $mainDetail->getStock() >= $mainDetail->getMinPurchase();
    }
}
