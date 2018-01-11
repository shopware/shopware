<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductSearchKeyword;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductSearchKeywordDefinition;

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
