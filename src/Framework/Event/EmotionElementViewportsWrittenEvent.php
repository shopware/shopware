<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class EmotionElementViewportsWrittenEvent extends WrittenEvent
{
    const NAME = 's_emotion_element_viewports.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emotion_element_viewports';
    }
}
