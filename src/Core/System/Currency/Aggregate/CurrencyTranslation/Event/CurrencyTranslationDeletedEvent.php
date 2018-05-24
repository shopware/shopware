<?php declare(strict_types=1);

namespace Shopware\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;

class CurrencyTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'currency_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyTranslationDefinition::class;
    }
}
