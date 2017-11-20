<?php declare(strict_types=1);

namespace Shopware\Category\Event\CategoryTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Category\Definition\CategoryTranslationDefinition;

class CategoryTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'category_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CategoryTranslationDefinition::class;
    }
}
