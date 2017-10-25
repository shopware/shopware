<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Searcher;

use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderLineItemSearchResult extends OrderLineItemBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
