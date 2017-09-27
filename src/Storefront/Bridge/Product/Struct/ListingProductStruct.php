<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ListingProductStruct extends ProductBasicStruct
{
    /**
     * @var ListingPriceStruct
     */
    protected $listingPrice;

    /**
     * @var ProductMediaBasicStruct
     */
    protected $cover;

    public function getListingPrice(): ListingPriceStruct
    {
        return $this->listingPrice;
    }

    public function setListingPrice(ListingPriceStruct $listingPrice): void
    {
        $this->listingPrice = $listingPrice;
    }

    public function getCover(): ?ProductMediaBasicStruct
    {
        return $this->cover;
    }

    public function setCover(?ProductMediaBasicStruct $cover)
    {
        $this->cover = $cover;
    }
}
