<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductStreamTab;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductStreamTabDefinition;

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
