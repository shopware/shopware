<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Searcher;

use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class PriceGroupDiscountSearchResult extends PriceGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
