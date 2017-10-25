<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class MediaWrittenEvent extends EntityWrittenEvent
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
