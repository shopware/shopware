<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSearchKeyword\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;

class ProductSearchKeywordDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_search_keyword.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductSearchKeywordDefinition::class;
    }
}
