<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceBasicCollection;

class ProductContextPriceSearchResult extends ProductContextPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
