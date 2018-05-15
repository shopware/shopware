<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Checkout\Order\Collection\OrderTransactionStateBasicCollection;

class OrderTransactionStateSearchResult extends OrderTransactionStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
