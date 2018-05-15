<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event\Currency;

use Shopware\System\Currency\Definition\CurrencyDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
