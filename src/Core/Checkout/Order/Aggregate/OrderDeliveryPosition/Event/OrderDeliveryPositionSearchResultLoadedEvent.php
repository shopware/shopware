<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderDeliveryPositionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.search.result.loaded';

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionSearchResult
     */
    protected $result;

    public function __construct(OrderDeliveryPositionSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
