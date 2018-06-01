<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Event;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
