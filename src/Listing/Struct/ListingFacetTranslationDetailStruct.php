<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class ListingFacetTranslationDetailStruct extends ListingFacetTranslationBasicStruct
{
    /**
     * @var ListingFacetBasicStruct
     */
    protected $listingFacet;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getListingFacet(): ListingFacetBasicStruct
    {
        return $this->listingFacet;
    }

    public function setListingFacet(ListingFacetBasicStruct $listingFacet): void
    {
        $this->listingFacet = $listingFacet;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
