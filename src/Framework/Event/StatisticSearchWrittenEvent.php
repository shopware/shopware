<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class StatisticSearchWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'statistic_search.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'statistic_search';
    }
}
