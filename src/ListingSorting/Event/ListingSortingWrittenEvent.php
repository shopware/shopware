<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ListingSortingWrittenEvent extends EntityWrittenEvent
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
