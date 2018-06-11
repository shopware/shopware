<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CustomerGroupDiscountSearchResult extends CustomerGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
