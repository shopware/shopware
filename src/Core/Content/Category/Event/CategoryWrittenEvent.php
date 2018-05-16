<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event;

use Shopware\Content\Category\CategoryDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CategoryWrittenEvent extends WrittenEvent
{
    public const NAME = 'category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryDefinition::class;
    }
}
