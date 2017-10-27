<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;

class OrderLineItemSearchResult extends OrderLineItemBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
