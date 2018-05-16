<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;

class MediaTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'media_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaTranslationDefinition::class;
    }
}
