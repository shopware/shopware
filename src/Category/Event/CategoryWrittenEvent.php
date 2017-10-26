<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CategoryWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'category';
    }
}
