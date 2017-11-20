<?php declare(strict_types=1);

namespace Shopware\Listing\Collection;

use Shopware\Listing\Struct\ListingFacetTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ListingFacetTranslationDetailCollection extends ListingFacetTranslationBasicCollection
{
    /**
     * @var ListingFacetTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getListingFacets(): ListingFacetBasicCollection
    {
        return new ListingFacetBasicCollection(
            $this->fmap(function (ListingFacetTranslationDetailStruct $listingFacetTranslation) {
                return $listingFacetTranslation->getListingFacet();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ListingFacetTranslationDetailStruct $listingFacetTranslation) {
                return $listingFacetTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetTranslationDetailStruct::class;
    }
}
