<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Api\Write\WrittenEvent;

class MediaWrittenEvent extends WrittenEvent
{
    const NAME = 'media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'media';
    }
}
