<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class StatisticSearchWrittenEvent extends EntityWrittenEvent
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
