<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event;

use Shopware\System\Currency\CurrencyDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CurrencyWrittenEvent extends WrittenEvent
{
    public const NAME = 'currency.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyDefinition::class;
    }
}
