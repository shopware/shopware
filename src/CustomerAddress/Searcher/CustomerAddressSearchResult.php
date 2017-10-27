<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;

class CustomerAddressSearchResult extends CustomerAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
