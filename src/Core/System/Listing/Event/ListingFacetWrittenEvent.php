<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Listing\ListingFacetDefinition;

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
