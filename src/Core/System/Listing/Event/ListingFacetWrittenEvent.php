<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Listing\ListingFacetDefinition;

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
