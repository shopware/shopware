<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\Currency;

use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
