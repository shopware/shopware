<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Api\Write\WrittenEvent;

class MediaAssociationWrittenEvent extends WrittenEvent
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
