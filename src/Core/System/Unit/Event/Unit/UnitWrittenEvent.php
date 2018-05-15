<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event\Unit;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Unit\Definition\UnitDefinition;

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
