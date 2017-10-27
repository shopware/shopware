<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;

class PriceGroupDiscountSearchResult extends PriceGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
