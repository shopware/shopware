<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductContextPriceSearchResult extends ProductContextPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
