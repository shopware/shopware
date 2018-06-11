<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Currency\CurrencyDefinition;

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
