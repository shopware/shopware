<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
