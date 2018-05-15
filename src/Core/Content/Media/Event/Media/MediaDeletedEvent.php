<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event\Media;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
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
