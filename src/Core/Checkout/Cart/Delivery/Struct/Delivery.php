<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Framework\Struct\Struct;

class Delivery extends Struct
{
    /**
     * @var DeliveryPositionCollection
     */
    protected $positions;

    /**
     * @var ShippingLocation
     */
    protected $location;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    /**
     * @var ShippingMethodStruct
     */
    protected $shippingMethod;

    /**
     * @var Price
     */
    protected $shippingCosts;

    /**
     * @var DeliveryDate
     */
    protected $endDeliveryDate;

    public function __construct(
        DeliveryPositionCollection $positions,
        DeliveryDate $deliveryDate,
        ShippingMethodStruct $shippingMethod,
        ShippingLocation $location,
        Price $shippingCosts
    ) {
        $this->location = $location;
        $this->positions = $positions;
        $this->deliveryDate = $deliveryDate;
        $this->shippingMethod = $shippingMethod;
        $this->shippingCosts = $shippingCosts;

        $end = clone $deliveryDate;

        $this->endDeliveryDate = new DeliveryDate(
            $end->getEarliest()->add(new \DateInterval('P' . $this->shippingMethod->getMinDeliveryTime() . 'D')),
            $end->getLatest()->add(new \DateInterval('P' . $this->shippingMethod->getMaxDeliveryTime() . 'D'))
        );
    }

    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }

    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getEndDeliveryDate(): DeliveryDate
    {
        return $this->endDeliveryDate;
    }

    public function getShippingMethod(): ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function getShippingCosts(): Price
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(Price $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }
}
