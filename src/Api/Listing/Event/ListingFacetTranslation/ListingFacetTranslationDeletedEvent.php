<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingFacetTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Listing\Definition\ListingFacetTranslationDefinition;

class ListingFacetTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'listing_facet_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingFacetTranslationDefinition::class;
    }
}
