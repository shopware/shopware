<?php declare(strict_types=1);

namespace Shopware\Category\Event\Category;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Category\Definition\CategoryDefinition;

class CategoryWrittenEvent extends WrittenEvent
{
    const NAME = 'category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryDefinition::class;
    }
}
