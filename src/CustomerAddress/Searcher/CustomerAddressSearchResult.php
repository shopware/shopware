<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Searcher;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CustomerAddressSearchResult extends CustomerAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
