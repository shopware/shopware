<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct;

use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Collection\OrderTransactionStateBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderTransactionStateSearchResult extends OrderTransactionStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
