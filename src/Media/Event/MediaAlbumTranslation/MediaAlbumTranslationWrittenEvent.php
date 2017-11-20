<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaAlbumTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Media\Definition\MediaAlbumTranslationDefinition;

class MediaAlbumTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'media_album_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumTranslationDefinition::class;
    }
}
