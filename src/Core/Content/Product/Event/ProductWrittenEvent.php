<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\ProductDefinition;

class ProductWrittenEvent extends WrittenEvent
{
    public const NAME = 'product.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }
}
