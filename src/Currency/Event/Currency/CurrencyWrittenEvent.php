<?php declare(strict_types=1);

namespace Shopware\Currency\Event\Currency;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Currency\Definition\CurrencyDefinition;

class CurrencyWrittenEvent extends WrittenEvent
{
    const NAME = 'currency.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyDefinition::class;
    }
}
