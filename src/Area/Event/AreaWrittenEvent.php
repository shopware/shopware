<?php declare(strict_types=1);

namespace Shopware\Area\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class AreaWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'area.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'area';
    }
}
