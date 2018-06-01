<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Event;

use Shopware\System\Touchpoint\TouchpointDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class TouchpointWrittenEvent extends WrittenEvent
{
    public const NAME = 'touchpoint.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TouchpointDefinition::class;
    }
}
