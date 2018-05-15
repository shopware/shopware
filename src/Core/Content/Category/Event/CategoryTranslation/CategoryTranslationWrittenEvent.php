<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\CategoryTranslation;

use Shopware\Content\Category\Definition\CategoryTranslationDefinition;
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
