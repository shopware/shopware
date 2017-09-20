<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Struct;

use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;

class OrderDeliveryDetailStruct extends OrderDeliveryBasicStruct
{
    /**
     * @var OrderDeliveryPositionBasicCollection
     */
    protected $positions;

    public function __construct()
    {
        $this->positions = new OrderDeliveryPositionBasicCollection();
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
