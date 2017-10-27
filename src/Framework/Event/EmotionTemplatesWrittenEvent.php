<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class EmotionTemplatesWrittenEvent extends WrittenEvent
{
    const NAME = 's_emotion_templates.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emotion_templates';
    }
}
