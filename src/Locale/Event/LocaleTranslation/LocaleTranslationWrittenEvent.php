<?php declare(strict_types=1);

namespace Shopware\Locale\Event\LocaleTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Locale\Definition\LocaleTranslationDefinition;

class LocaleTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'locale_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleTranslationDefinition::class;
    }
}
