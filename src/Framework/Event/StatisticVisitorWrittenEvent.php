<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class StatisticVisitorWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'statistic_visitor.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'statistic_visitor';
    }
}
