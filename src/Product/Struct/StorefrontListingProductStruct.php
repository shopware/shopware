<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\ProductPrice\Struct\ProductListingPrice;

class StorefrontListingProductStruct extends StorefrontBasicProductStruct
{
    /**
     * @var ProductListingPrice
     */
    protected $listingPrice;

    public function getListingPrice(): ProductListingPrice
    {
        return $this->listingPrice;
    }

    public function setListingPrice(ProductListingPrice $listingPrice): void
    {
        $this->listingPrice = $listingPrice;
    }
}