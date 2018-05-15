<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Content\Product\Collection\ProductSearchKeywordBasicCollection;

class ProductSearchKeywordSearchResult extends ProductSearchKeywordBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
