<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
