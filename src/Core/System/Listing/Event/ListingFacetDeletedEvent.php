<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Listing\ListingFacetDefinition;

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
