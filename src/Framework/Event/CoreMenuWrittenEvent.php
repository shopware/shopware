<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CoreMenuWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_core_menu.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_core_menu';
    }
}
