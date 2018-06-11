<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CustomerAddressSearchResult extends CustomerAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
