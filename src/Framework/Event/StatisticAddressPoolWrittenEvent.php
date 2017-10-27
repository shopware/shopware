<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class StatisticAddressPoolWrittenEvent extends WrittenEvent
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
