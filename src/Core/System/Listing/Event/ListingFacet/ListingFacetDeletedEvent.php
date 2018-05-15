<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacet;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Listing\Definition\ListingFacetDefinition;

class ListingFacetDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'listing_facet.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingFacetDefinition::class;
    }
}
