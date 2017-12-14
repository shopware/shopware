<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;

class OrderDeliveryBasicStruct extends Entity
{
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
     * @var string|null
     */
    protected $trackingCode;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderAddressBasicStruct
     */
    protected $shippingAddress;

    /**
     * @var OrderStateBasicStruct
     */
    protected $orderState;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

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

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(?string $trackingCode): void
    {
        $this->trackingCode = $trackingCode;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getShippingAddress(): OrderAddressBasicStruct
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(OrderAddressBasicStruct $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getOrderState(): OrderStateBasicStruct
    {
        return $this->orderState;
    }

    public function setOrderState(OrderStateBasicStruct $orderState): void
    {
        $this->orderState = $orderState;
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
