<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\Core\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Core\Framework\ORM\Entity;

class OrderDeliveryBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $shippingAddressId;

    /**
     * @var string
     */
    protected $orderStateId;

    /**
     * @var string
     */
    protected $shippingMethodId;

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
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct
     */
    protected $orderState;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getShippingAddressId(): string
    {
        return $this->shippingAddressId;
    }

    public function setShippingAddressId(string $shippingAddressId): void
    {
        $this->shippingAddressId = $shippingAddressId;
    }

    public function getOrderStateId(): string
    {
        return $this->orderStateId;
    }

    public function setOrderStateId(string $orderStateId): void
    {
        $this->orderStateId = $orderStateId;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
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

    public function getOrderState(): \Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct
    {
        return $this->orderState;
    }

    public function setOrderState(\Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct $orderState): void
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
