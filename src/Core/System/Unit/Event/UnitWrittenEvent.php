<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Unit\UnitDefinition;

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
