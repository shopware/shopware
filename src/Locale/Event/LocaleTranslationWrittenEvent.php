<?php declare(strict_types=1);

namespace Shopware\Locale\Event;

use Shopware\Api\Write\WrittenEvent;

class LocaleTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'locale_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'locale_translation';
    }
}
