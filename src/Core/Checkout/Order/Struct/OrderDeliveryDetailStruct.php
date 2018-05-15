<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Checkout\Order\Collection\OrderDeliveryPositionBasicCollection;

class OrderDeliveryDetailStruct extends OrderDeliveryBasicStruct
{
    /**
     * @var OrderBasicStruct
     */
    protected $order;

    /**
     * @var OrderDeliveryPositionBasicCollection
     */
    protected $positions;

    public function __construct()
    {
        $this->positions = new OrderDeliveryPositionBasicCollection();
    }

    public function getOrder(): OrderBasicStruct
    {
        return $this->order;
    }

    public function setOrder(OrderBasicStruct $order): void
    {
        $this->order = $order;
    }

    public function getPositions(): OrderDeliveryPositionBasicCollection
    {
        return $this->positions;
    }

    public function setPositions(OrderDeliveryPositionBasicCollection $positions): void
    {
        $this->positions = $positions;
    }
}
