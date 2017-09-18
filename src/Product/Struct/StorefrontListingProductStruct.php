<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\ProductPrice\Struct\ProductListingPrice;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

class StorefrontListingProductStruct extends ProductBasicStruct
{
    /**
     * @var ProductPriceBasicCollection
     */
    protected $prices;

    /**
     * @var ProductListingPrice
     */
    protected $listingPrice;

    public function getPrices(): ProductPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getListingPrice(): ProductListingPrice
    {
        return $this->listingPrice;
    }

    public function setListingPrice(ProductListingPrice $listingPrice): void
    {
        $this->listingPrice = $listingPrice;
    }
}