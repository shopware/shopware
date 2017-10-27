<?php declare(strict_types=1);

namespace Shopware\Unit\Event;

use Shopware\Api\Write\WrittenEvent;

class UnitWrittenEvent extends WrittenEvent
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
