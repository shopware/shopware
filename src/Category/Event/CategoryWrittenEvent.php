<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Api\Write\WrittenEvent;

class CategoryWrittenEvent extends WrittenEvent
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
