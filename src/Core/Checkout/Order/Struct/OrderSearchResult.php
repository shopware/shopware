<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderSearchResult extends OrderBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
