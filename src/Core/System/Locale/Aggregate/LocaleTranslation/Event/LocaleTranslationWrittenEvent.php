<?php declare(strict_types=1);

namespace Shopware\System\Locale\Aggregate\LocaleTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;

class LocaleTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'locale_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleTranslationDefinition::class;
    }
}
