<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;

class CustomerGroupDiscountSearchResult extends CustomerGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
