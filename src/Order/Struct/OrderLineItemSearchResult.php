<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Order\Collection\OrderLineItemBasicCollection;

class OrderLineItemSearchResult extends OrderLineItemBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
