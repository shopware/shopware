<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductSearchKeyword;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductSearchKeywordDefinition;

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
