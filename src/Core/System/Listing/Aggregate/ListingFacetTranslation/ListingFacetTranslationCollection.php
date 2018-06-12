<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class ListingFacetTranslationCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingFacetTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ListingFacetTranslationStruct
    {
        return parent::current();
    }

    public function getListingFacetIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getListingFacetId();
        });
    }

    public function filterByListingFacetId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationStruct $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getListingFacetId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationStruct $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetTranslationStruct::class;
    }
}
