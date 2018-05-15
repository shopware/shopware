<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Media\MediaDefinition;

class MediaWrittenEvent extends WrittenEvent
{
    public const NAME = 'media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }
}
