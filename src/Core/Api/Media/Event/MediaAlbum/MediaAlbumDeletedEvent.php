<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbum;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Media\Definition\MediaAlbumDefinition;

class MediaAlbumDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'media_album.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumDefinition::class;
    }
}
