<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderState\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;

class OrderStateSearchResult extends OrderStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
