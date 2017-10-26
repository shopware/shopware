<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class StatisticAddressPoolWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'statistic_address_pool.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'statistic_address_pool';
    }
}
