<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingFacetTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Listing\Definition\ListingFacetTranslationDefinition;

class ListingFacetTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'listing_facet_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingFacetTranslationDefinition::class;
    }
}
