<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event\CurrencyTranslation;

use Shopware\System\Currency\Definition\CurrencyTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
