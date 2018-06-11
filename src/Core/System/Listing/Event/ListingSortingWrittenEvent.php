<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Listing\ListingSortingDefinition;

class ListingSortingWrittenEvent extends WrittenEvent
{
    public const NAME = 'listing_sorting.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingDefinition::class;
    }
}
