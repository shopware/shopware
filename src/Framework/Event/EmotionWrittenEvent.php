<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class EmotionWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_emotion.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emotion';
    }
}
