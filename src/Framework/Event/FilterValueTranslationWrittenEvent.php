<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class FilterValueTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'filter_value_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'filter_value_translation';
    }
}
