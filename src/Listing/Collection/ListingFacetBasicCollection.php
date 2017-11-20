<?php declare(strict_types=1);

namespace Shopware\Listing\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Listing\Struct\ListingFacetBasicStruct;

class ListingFacetBasicCollection extends EntityCollection
{
    /**
     * @var ListingFacetBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ListingFacetBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ListingFacetBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetBasicStruct::class;
    }
}
