<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Listing\Struct\ListingFacetTranslationBasicStruct;

class ListingFacetTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ListingFacetTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ListingFacetTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ListingFacetTranslationBasicStruct
    {
        return parent::current();
    }

    public function getListingFacetUuids(): array
    {
        return $this->fmap(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getListingFacetUuid();
        });
    }

    public function filterByListingFacetUuid(string $uuid): ListingFacetTranslationBasicCollection
    {
        return $this->filter(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) use ($uuid) {
            return $listingFacetTranslation->getListingFacetUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) {
            return $listingFacetTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): ListingFacetTranslationBasicCollection
    {
        return $this->filter(function (ListingFacetTranslationBasicStruct $listingFacetTranslation) use ($uuid) {
            return $listingFacetTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetTranslationBasicStruct::class;
    }
}
