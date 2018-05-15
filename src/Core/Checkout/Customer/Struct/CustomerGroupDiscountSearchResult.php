<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerGroupDiscountSearchResult extends CustomerGroupDiscountBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
