<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Tax\TaxDefinition;

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
