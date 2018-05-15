<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event\Media;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Media\Definition\MediaDefinition;

class MediaDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'media.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }
}
