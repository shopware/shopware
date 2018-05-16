<?php declare(strict_types=1);

namespace Shopware\Content\Category\Aggregate\CategoryTranslation\Event;

use Shopware\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CategoryTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'category_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryTranslationDefinition::class;
    }
}
