<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingFacetTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Listing\Definition\ListingFacetTranslationDefinition;

class ListingFacetTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'listing_facet_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingFacetTranslationDefinition::class;
    }
}
