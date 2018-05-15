<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\Category;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CategoryDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'category.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryDefinition::class;
    }
}
