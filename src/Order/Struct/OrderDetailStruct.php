<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;

class OrderDetailStruct extends OrderBasicStruct
{
    /**
     * @var OrderLineItemBasicCollection
     */
    protected $lineItems;

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $deliveries;

    public function __construct()
    {
        $this->lineItems = new OrderLineItemBasicCollection();
        $this->deliveries = new OrderDeliveryBasicCollection();
    }

    public function getLineItems(): OrderLineItemBasicCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(OrderLineItemBasicCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->deliveries;
    }

    public function setDeliveries(OrderDeliveryBasicCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }
}
