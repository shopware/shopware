<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Checkout\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Checkout\Order\Collection\OrderLineItemBasicCollection;
use Shopware\Checkout\Order\Collection\OrderTransactionBasicCollection;

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

    /**
     * @var OrderTransactionBasicCollection
     */
    protected $transactions;

    public function __construct()
    {
        $this->deliveries = new OrderDeliveryBasicCollection();

        $this->lineItems = new OrderLineItemBasicCollection();

        $this->transactions = new OrderTransactionBasicCollection();
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

    public function getTransactions(): OrderTransactionBasicCollection
    {
        return $this->transactions;
    }

    public function setTransactions(OrderTransactionBasicCollection $transactions): void
    {
        $this->transactions = $transactions;
    }
}
