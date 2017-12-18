<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\CurrencyTranslation;

use Shopware\Api\Currency\Definition\CurrencyTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CurrencyTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'currency_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CurrencyTranslationDefinition::class;
    }
}
