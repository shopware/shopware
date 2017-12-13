<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Order\Collection\OrderStateBasicCollection;

class OrderStateSearchResult extends OrderStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
