<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class MediaAssociationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_media_association.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_media_association';
    }
}
