<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
