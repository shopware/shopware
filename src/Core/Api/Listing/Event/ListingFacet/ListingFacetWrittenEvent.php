<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingFacet;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Listing\Definition\ListingFacetDefinition;

class ListingFacetWrittenEvent extends WrittenEvent
{
    public const NAME = 'listing_facet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingFacetDefinition::class;
    }
}
