<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Searcher;

use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
