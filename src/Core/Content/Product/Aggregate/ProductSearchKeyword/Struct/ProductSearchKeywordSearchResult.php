<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Collection\ProductSearchKeywordBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductSearchKeywordSearchResult extends ProductSearchKeywordBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
