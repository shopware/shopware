<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Order\Collection\OrderTransactionBasicCollection;

class OrderTransactionSearchResult extends OrderTransactionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
