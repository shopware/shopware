<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Collection\OrderTransactionStateBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderTransactionStateSearchResult extends OrderTransactionStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
