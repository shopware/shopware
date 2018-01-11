<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\CategoryTranslation;

use Shopware\Api\Category\Definition\CategoryTranslationDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

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
