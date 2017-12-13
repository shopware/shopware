<?php declare(strict_types=1);

namespace Shopware\Media\Event\Media;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Media\Definition\MediaDefinition;

class MediaWrittenEvent extends WrittenEvent
{
    const NAME = 'media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }
}
