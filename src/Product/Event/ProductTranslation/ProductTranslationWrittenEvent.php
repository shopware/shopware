<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductTranslationDefinition;

class ProductTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'product_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductTranslationDefinition::class;
    }
}
