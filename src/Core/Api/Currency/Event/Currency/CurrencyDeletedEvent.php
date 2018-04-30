<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\Currency;

use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class CurrencyDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'currency.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyDefinition::class;
    }
}
