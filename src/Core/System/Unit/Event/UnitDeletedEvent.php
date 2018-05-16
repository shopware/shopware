<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Unit\UnitDefinition;

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
