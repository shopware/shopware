<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class FilterWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'filter.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'filter';
    }
}
