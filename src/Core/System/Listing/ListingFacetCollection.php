<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingFacetCollection extends EntityCollection
{
    /**
     * @var ListingFacetEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingFacetEntity
    {
        return parent::get($id);
    }

    public function current(): ListingFacetEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetEntity::class;
    }
}
