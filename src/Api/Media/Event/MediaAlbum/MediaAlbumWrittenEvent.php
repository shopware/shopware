<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbum;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Media\Definition\MediaAlbumDefinition;

class MediaAlbumWrittenEvent extends WrittenEvent
{
    public const NAME = 'media_album.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumDefinition::class;
    }
}
