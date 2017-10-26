<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class EmotionElementValueWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_emotion_element_value.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emotion_element_value';
    }
}
