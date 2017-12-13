<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSorting;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Listing\Definition\ListingSortingDefinition;

class ListingSortingWrittenEvent extends WrittenEvent
{
    const NAME = 'listing_sorting.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingDefinition::class;
    }
}
