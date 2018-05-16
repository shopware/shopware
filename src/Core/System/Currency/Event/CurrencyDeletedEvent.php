<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event;

use Shopware\System\Currency\CurrencyDefinition;
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
