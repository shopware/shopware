<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
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
     * @var ShippingMethodEntity
     */
    protected $shippingMethod;

    /**
     * @var CalculatedPrice
     */
    protected $shippingCosts;

    /**
     * @var DeliveryDate
     */
    protected $endDeliveryDate;

    /**
     * @var Error|null
     */
    protected $error;

    public function __construct(
        DeliveryPositionCollection $positions,
        DeliveryDate $deliveryDate,
        ShippingMethodEntity $shippingMethod,
        ShippingLocation $location,
        CalculatedPrice $shippingCosts
    ) {
        $this->location = $location;
        $this->positions = $positions;
        $this->deliveryDate = $deliveryDate;
        $this->shippingMethod = $shippingMethod;
        $this->shippingCosts = $shippingCosts;

        $end = clone $deliveryDate;

        $deliveryTime = $this->shippingMethod->getDeliveryTime();

        $this->endDeliveryDate = new DeliveryDate(
            $end->getEarliest()->add(new \DateInterval('P' . $deliveryTime->getMin() . 'D')),
            $end->getLatest()->add(new \DateInterval('P' . $deliveryTime->getMax() . 'D'))
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

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(CalculatedPrice $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }

    public function setError(?Error $error): void
    {
        $this->error = $error;
    }

    public function getError(): ?Error
    {
        return $this->error;
    }
}
