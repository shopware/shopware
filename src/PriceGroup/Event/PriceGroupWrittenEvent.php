<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Api\Write\WrittenEvent;

class PriceGroupWrittenEvent extends WrittenEvent
{
    const NAME = 'price_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'price_group';
    }
}
