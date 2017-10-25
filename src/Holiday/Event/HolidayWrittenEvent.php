<?php declare(strict_types=1);

namespace Shopware\Holiday\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class HolidayWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'holiday.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'holiday';
    }
}
