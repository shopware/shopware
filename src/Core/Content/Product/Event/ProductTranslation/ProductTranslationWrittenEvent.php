<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductTranslation;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductTranslationDefinition;

class ProductTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductTranslationDefinition::class;
    }
}
