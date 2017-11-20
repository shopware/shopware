<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaAlbum;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Media\Definition\MediaAlbumDefinition;

class MediaAlbumWrittenEvent extends WrittenEvent
{
    const NAME = 'media_album.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumDefinition::class;
    }
}
