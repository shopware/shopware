<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliverySearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderDeliverySearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery.search.result.loaded';

    /**
     * @var OrderDeliverySearchResult
     */
    protected $result;

    public function __construct(OrderDeliverySearchResult $result)
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
