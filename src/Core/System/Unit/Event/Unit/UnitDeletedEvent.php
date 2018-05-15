<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event\Unit;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Unit\Definition\UnitDefinition;

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
