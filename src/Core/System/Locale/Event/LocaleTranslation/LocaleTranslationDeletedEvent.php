<?php declare(strict_types=1);

namespace Shopware\System\Locale\Event\LocaleTranslation;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Locale\Definition\LocaleTranslationDefinition;

class LocaleTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'locale_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleTranslationDefinition::class;
    }
}
