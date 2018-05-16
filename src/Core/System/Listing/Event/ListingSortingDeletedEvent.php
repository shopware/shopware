<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Listing\ListingSortingDefinition;

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
