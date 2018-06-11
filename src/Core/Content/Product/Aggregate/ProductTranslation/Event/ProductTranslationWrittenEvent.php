<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
