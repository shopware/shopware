<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(ListingFacetEntity $entity)
 * @method void                    set(string $key, ListingFacetEntity $entity)
 * @method ListingFacetEntity[]    getIterator()
 * @method ListingFacetEntity[]    getElements()
 * @method ListingFacetEntity|null get(string $key)
 * @method ListingFacetEntity|null first()
 * @method ListingFacetEntity|null last()
 */
class ListingFacetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ListingFacetEntity::class;
    }
}
