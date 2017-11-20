<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Media\Definition\MediaTranslationDefinition;

class MediaTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'media_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaTranslationDefinition::class;
    }
}
