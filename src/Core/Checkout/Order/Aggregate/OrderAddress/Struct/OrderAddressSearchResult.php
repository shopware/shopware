<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
