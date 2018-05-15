<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
