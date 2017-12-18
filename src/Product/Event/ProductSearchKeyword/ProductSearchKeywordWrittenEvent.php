<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductSearchKeyword;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductSearchKeywordDefinition;

class ProductSearchKeywordWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_search_keyword.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductSearchKeywordDefinition::class;
    }
}
