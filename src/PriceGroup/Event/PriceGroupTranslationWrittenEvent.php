<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Api\Write\WrittenEvent;

class PriceGroupTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'price_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'price_group_translation';
    }
}
