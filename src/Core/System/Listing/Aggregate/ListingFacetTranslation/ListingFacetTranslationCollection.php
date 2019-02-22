<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(ListingFacetTranslationEntity $entity)
 * @method void                               set(string $key, ListingFacetTranslationEntity $entity)
 * @method ListingFacetTranslationEntity[]    getIterator()
 * @method ListingFacetTranslationEntity[]    getElements()
 * @method ListingFacetTranslationEntity|null get(string $key)
 * @method ListingFacetTranslationEntity|null first()
 * @method ListingFacetTranslationEntity|null last()
 */
class ListingFacetTranslationCollection extends EntityCollection
{
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
