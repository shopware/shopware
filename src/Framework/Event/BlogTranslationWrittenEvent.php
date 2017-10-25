<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class BlogTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'blog_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'blog_translation';
    }
}
