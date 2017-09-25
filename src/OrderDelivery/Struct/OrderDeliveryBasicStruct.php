<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class OrderDeliveryBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $orderUuid;

    /**
     * @var string
     */
    protected $shippingAddressUuid;

    /**
     * @var string
     */
    protected $orderStateUuid;

    /**
     * @var string|null
     */
    protected $trackingCode;

    /**
     * @var string
     */
    protected $shippingMethodUuid;

    /**
     * @var \DateTime
     */
    protected $shippingDateEarliest;

    /**
     * @var \DateTime
     */
    protected $shippingDateLatest;

    /**
     * @var string
     */
    protected $payload;

    /**
     * @var OrderStateBasicStruct
     */
    protected $state;

    /**
     * @var OrderAddressBasicStruct
     */
    protected $shippingAddress;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getOrderUuid(): string
    {
        return $this->orderUuid;
    }

    public function setOrderUuid(string $orderUuid): void
    {
        $this->orderUuid = $orderUuid;
    }

    public function getShippingAddressUuid(): string
    {
        return $this->shippingAddressUuid;
    }

    public function setShippingAddressUuid(string $shippingAddressUuid): void
    {
        $this->shippingAddressUuid = $shippingAddressUuid;
    }

    public function getOrderStateUuid(): string
    {
        return $this->orderStateUuid;
    }

    public function setOrderStateUuid(string $orderStateUuid): void
    {
        $this->orderStateUuid = $orderStateUuid;
    }

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(?string $trackingCode): void
    {
        $this->trackingCode = $trackingCode;
    }

    public function getShippingMethodUuid(): string
    {
        return $this->shippingMethodUuid;
    }

    public function setShippingMethodUuid(string $shippingMethodUuid): void
    {
        $this->shippingMethodUuid = $shippingMethodUuid;
    }

    public function getShippingDateEarliest(): \DateTime
    {
        return $this->shippingDateEarliest;
    }

    public function setShippingDateEarliest(\DateTime $shippingDateEarliest): void
    {
        $this->shippingDateEarliest = $shippingDateEarliest;
    }

    public function getShippingDateLatest(): \DateTime
    {
        return $this->shippingDateLatest;
    }

    public function setShippingDateLatest(\DateTime $shippingDateLatest): void
    {
        $this->shippingDateLatest = $shippingDateLatest;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    public function getState(): OrderStateBasicStruct
    {
        return $this->state;
    }

    public function setState(OrderStateBasicStruct $state): void
    {
        $this->state = $state;
    }

    public function getShippingAddress(): OrderAddressBasicStruct
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(OrderAddressBasicStruct $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }
}
