<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Api\Write\WrittenEvent;

class MediaTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'media_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'media_translation';
    }
}
