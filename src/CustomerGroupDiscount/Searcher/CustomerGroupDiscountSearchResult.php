<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Searcher;

use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CustomerGroupDiscountSearchResult extends CustomerGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
