<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\CategoryTranslation;

use Shopware\Api\Category\Definition\CategoryTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
