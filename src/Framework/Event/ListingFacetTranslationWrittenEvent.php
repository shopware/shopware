<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ListingFacetTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'listing_facet_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'listing_facet_translation';
    }
}
