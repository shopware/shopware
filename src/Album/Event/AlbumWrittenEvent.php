<?php declare(strict_types=1);

namespace Shopware\Album\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class AlbumWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'album.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'album';
    }
}
