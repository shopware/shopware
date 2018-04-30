<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
