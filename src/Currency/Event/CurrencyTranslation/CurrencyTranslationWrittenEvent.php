<?php declare(strict_types=1);

namespace Shopware\Currency\Event\CurrencyTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Currency\Definition\CurrencyTranslationDefinition;

class CurrencyTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'currency_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyTranslationDefinition::class;
    }
}
