<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductStreamTab;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductStreamTabDefinition;

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
