<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
