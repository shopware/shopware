<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class MediaTranslationWrittenEvent extends AbstractWrittenEvent
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
