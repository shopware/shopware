<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductStreamTab;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductStreamTabDefinition;

class ProductStreamTabWrittenEvent extends WrittenEvent
{
    const NAME = 'product_stream_tab.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamTabDefinition::class;
    }
}
