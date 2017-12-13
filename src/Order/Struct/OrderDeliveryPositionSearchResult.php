<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Order\Collection\OrderDeliveryPositionBasicCollection;

class OrderDeliveryPositionSearchResult extends OrderDeliveryPositionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
