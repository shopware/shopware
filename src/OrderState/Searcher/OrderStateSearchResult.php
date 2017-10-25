<?php declare(strict_types=1);

namespace Shopware\OrderState\Searcher;

use Shopware\OrderState\Struct\OrderStateBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderStateSearchResult extends OrderStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
