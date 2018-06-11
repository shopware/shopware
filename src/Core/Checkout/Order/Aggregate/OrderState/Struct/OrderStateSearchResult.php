<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderStateSearchResult extends OrderStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
