<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderTransactionSearchResult extends OrderTransactionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
