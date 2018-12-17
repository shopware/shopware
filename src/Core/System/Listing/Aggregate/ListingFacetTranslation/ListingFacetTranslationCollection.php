<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingFacetTranslationCollection extends EntityCollection
{
    /**
     * @var ListingFacetTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingFacetTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): ListingFacetTranslationEntity
    {
        return parent::current();
    }

    public function getListingFacetIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationEntity $listingFacetTranslation) {
            return $listingFacetTranslation->getListingFacetId();
        });
    }

    public function filterByListingFacetId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationEntity $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getListingFacetId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingFacetTranslationEntity $listingFacetTranslation) {
            return $listingFacetTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingFacetTranslationEntity $listingFacetTranslation) use ($id) {
            return $listingFacetTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetTranslationEntity::class;
    }
}
