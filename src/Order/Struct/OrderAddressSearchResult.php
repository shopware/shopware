<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Order\Collection\OrderAddressBasicCollection;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
