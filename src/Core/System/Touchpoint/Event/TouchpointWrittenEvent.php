<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Event;

use Shopware\Core\System\Touchpoint\TouchpointDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
