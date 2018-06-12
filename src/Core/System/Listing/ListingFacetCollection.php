<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\ORM\EntityCollection;


class ListingFacetCollection extends EntityCollection
{
    /**
     * @var ListingFacetStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingFacetStruct
    {
        return parent::get($id);
    }

    public function current(): ListingFacetStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetStruct::class;
    }
}
