<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ListingFacetWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'listing_facet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'listing_facet';
    }
}
