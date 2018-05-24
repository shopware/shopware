<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStreamTab\Event;

use Shopware\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ProductStreamTabWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_stream_tab.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamTabDefinition::class;
    }
}
