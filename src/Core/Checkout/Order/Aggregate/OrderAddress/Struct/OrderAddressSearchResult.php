<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderAddress\Struct;

use Shopware\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
