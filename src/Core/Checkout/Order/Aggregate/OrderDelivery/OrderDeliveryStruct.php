<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class OrderDeliveryStruct extends Entity
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
     * @var OrderAddressStruct
     */
    protected $shippingAddress;

    /**
     * @var OrderStateStruct
     */
    protected $orderState;

    /**
     * @var ShippingMethodStruct
     */
    protected $shippingMethod;

    /**
     * @var OrderStruct|null
     */
    protected $order;

    /**
     * @var null|EntitySearchResult
     */
    protected $positions;

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

    public function getShippingAddress(): OrderAddressStruct
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(OrderAddressStruct $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getOrderState(): OrderStateStruct
    {
        return $this->orderState;
    }

    public function setOrderState(OrderStateStruct $orderState): void
    {
        $this->orderState = $orderState;
    }

    public function getShippingMethod(): ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getOrder(): ?OrderStruct
    {
        return $this->order;
    }

    public function setOrder(OrderStruct $order): void
    {
        $this->order = $order;
    }

    public function getPositions(): ?EntitySearchResult
    {
        return $this->positions;
    }

    public function setPositions(EntitySearchResult $positions): void
    {
        $this->positions = $positions;
    }
}
