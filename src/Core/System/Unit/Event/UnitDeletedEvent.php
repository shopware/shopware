<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Unit\UnitDefinition;

class UnitDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'unit.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UnitDefinition::class;
    }
}
