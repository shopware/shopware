<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Struct;

use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderSearchResult extends OrderBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
