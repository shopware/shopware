<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Event\Unit;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Unit\Definition\UnitDefinition;

class UnitWrittenEvent extends WrittenEvent
{
    public const NAME = 'unit.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UnitDefinition::class;
    }
}
