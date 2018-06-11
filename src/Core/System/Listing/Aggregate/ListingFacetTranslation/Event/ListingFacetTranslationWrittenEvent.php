<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Listing\Definition\ListingFacetTranslationDefinition;

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
