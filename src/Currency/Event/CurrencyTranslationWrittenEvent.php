<?php declare(strict_types=1);

namespace Shopware\Currency\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CurrencyTranslationWrittenEvent extends AbstractWrittenEvent
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
