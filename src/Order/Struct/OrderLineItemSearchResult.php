<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Order\Collection\OrderLineItemBasicCollection;

class OrderLineItemSearchResult extends OrderLineItemBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
