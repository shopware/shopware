<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
