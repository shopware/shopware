<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Core\Checkout\Order\Struct\OrderBasicStruct;

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
