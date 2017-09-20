<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
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
