<?php declare(strict_types=1);

namespace Shopware\Area\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class AreaWrittenEvent extends AbstractWrittenEvent
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
