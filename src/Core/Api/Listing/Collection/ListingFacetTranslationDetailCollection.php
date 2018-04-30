<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Api\Listing\Struct\ListingFacetTranslationDetailStruct;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
