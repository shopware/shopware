<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class PriceGroupWrittenEvent extends AbstractWrittenEvent
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
