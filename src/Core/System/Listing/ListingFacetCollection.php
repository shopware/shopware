<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingFacetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ListingFacetEntity::class;
    }
}
