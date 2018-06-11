<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderLineItemBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_line_item.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemBasicCollection
     */
    protected $orderLineItems;

    public function __construct(OrderLineItemBasicCollection $orderLineItems, Context $context)
    {
        $this->context = $context;
        $this->orderLineItems = $orderLineItems;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderLineItems(): OrderLineItemBasicCollection
    {
        return $this->orderLineItems;
    }
}
