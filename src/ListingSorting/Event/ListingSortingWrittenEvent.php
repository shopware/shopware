<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Event;

use Shopware\Api\Write\WrittenEvent;

class ListingSortingWrittenEvent extends WrittenEvent
{
    const NAME = 'listing_sorting.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'listing_sorting';
    }
}
