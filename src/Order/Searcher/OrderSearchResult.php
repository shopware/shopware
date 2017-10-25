<?php declare(strict_types=1);

namespace Shopware\Order\Searcher;

use Shopware\Order\Struct\OrderBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderSearchResult extends OrderBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
