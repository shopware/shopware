<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CustomerAddressSearchResult extends CustomerAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
