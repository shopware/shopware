<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Event;

use Shopware\Core\System\Touchpoint\TouchpointDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
