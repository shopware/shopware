<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\CategoryTranslation;

use Shopware\Content\Category\Definition\CategoryTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CategoryTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'category_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryTranslationDefinition::class;
    }
}
