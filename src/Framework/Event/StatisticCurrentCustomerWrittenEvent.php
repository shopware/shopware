<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class StatisticCurrentCustomerWrittenEvent extends WrittenEvent
{
    const NAME = 'statistic_current_customer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'statistic_current_customer';
    }
}
