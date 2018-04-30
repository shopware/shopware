<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbumTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Media\Definition\MediaAlbumTranslationDefinition;

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
