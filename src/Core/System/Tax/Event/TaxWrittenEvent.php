<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Tax\TaxDefinition;

class TaxWrittenEvent extends WrittenEvent
{
    public const NAME = 'tax.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxDefinition::class;
    }
}
