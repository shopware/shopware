<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSorting;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Listing\Definition\ListingSortingDefinition;

class ListingSortingDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'listing_sorting.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingDefinition::class;
    }
}
