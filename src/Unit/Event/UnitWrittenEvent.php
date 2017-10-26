<?php declare(strict_types=1);

namespace Shopware\Unit\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class UnitWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'unit.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'unit';
    }
}
