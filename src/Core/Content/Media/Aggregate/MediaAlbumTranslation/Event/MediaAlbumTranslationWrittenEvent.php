<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Event;

use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class MediaAlbumTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'media_album_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumTranslationDefinition::class;
    }
}
