<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\LocaleTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Locale\Definition\LocaleTranslationDefinition;

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
