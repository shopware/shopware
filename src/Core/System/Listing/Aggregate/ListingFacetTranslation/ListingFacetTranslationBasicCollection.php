<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationBasicStruct;

class ListingFacetTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingFacetTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ListingFacetTranslationBasicStruct
    {
        return parent::current();
    }

    public function getListingFacetIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getListingFacetId();
        });
    }

    public function filterByListingFacetId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getListingFacetId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetTranslationBasicStruct::class;
    }
}
