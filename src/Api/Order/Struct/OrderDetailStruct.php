<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Api\Order\Collection\OrderLineItemBasicCollection;

class OrderDetailStruct extends OrderBasicStruct
{
    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $deliveries;

    /**
     * @var OrderLineItemBasicCollection
     */
    protected $lineItems;

    public function __construct()
    {
        $this->deliveries = new OrderDeliveryBasicCollection();

        $this->lineItems = new OrderLineItemBasicCollection();
    }

    public function getDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->deliveries;
    }

    public function setDeliveries(OrderDeliveryBasicCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    public function getLineItems(): OrderLineItemBasicCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(OrderLineItemBasicCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }
}
