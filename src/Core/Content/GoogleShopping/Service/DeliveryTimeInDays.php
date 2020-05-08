<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class DeliveryTimeInDays
{
    /**
     * @var int
     */
    protected $min;

    /**
     * @var int
     */
    protected $max;

    public function __construct(DeliveryTimeEntity $deliveryTime)
    {
        $this->min = $this->convertDeliveryTimeInDays($deliveryTime, $deliveryTime->getMin());
        $this->max = $this->convertDeliveryTimeInDays($deliveryTime, $deliveryTime->getMax());
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    private function convertDeliveryTimeInDays(DeliveryTimeEntity $deliveryTime, $value): int
    {
        switch ($deliveryTime->getUnit()) {
            case DeliveryTimeEntity::DELIVERY_TIME_DAY:
                return $value;
            case DeliveryTimeEntity::DELIVERY_TIME_WEEK:
                return $value * 7;
            case DeliveryTimeEntity::DELIVERY_TIME_MONTH:
                return $value * 30;
            default:
                return $value * 365;
        }
    }
}
