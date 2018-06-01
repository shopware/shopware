<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Event;

use Shopware\System\Touchpoint\TouchpointDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class TouchpointDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'touchpoint.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TouchpointDefinition::class;
    }
}
