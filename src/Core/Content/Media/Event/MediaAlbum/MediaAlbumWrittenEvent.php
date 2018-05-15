<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event\MediaAlbum;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Media\Definition\MediaAlbumDefinition;

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
