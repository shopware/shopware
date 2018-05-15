<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event\MediaTranslation;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Media\Definition\MediaTranslationDefinition;

class MediaTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'media_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaTranslationDefinition::class;
    }
}
