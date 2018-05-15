<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductTranslationDefinition;

class ProductTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductTranslationDefinition::class;
    }
}
