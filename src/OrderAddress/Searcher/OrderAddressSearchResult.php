<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
