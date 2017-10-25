<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class FilterOptionWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'filter_option.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'filter_option';
    }
}
