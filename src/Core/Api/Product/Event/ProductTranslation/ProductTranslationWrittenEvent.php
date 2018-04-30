<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductTranslationDefinition;

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
