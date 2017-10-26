<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class BlogTagWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'blog_tag.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'blog_tag';
    }
}
