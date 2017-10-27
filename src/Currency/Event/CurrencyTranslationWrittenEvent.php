<?php declare(strict_types=1);

namespace Shopware\Currency\Event;

use Shopware\Api\Write\WrittenEvent;

class CurrencyTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'currency_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'currency_translation';
    }
}
