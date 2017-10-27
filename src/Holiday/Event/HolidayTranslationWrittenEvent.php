<?php declare(strict_types=1);

namespace Shopware\Holiday\Event;

use Shopware\Api\Write\WrittenEvent;

class HolidayTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'holiday_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'holiday_translation';
    }
}
