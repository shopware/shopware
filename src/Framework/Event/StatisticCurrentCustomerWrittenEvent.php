<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class StatisticCurrentCustomerWrittenEvent extends AbstractWrittenEvent
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
