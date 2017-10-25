<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class PriceGroupWrittenEvent extends EntityWrittenEvent
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
