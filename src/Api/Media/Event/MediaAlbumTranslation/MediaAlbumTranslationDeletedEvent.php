<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaAlbumTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Media\Definition\MediaAlbumTranslationDefinition;

class MediaAlbumTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'media_album_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaAlbumTranslationDefinition::class;
    }
}
