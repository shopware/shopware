<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class EmotionCategoriesWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_emotion_categories.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_emotion_categories';
    }
}
