<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressStruct;
use Shopware\Core\System\Touchpoint\TouchpointStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Currency\CurrencyStruct;

class OrderStruct extends Entity
{
    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var string
     */
    protected $stateId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $touchpointId;

    /**
     * @var string
     */
    protected $billingAddressId;

    /**
     * @var \DateTime
     */
    protected $date;

    /**
     * @var float
     */
    protected $amountTotal;

    /**
     * @var float
     */
    protected $positionPrice;

    /**
     * @var float
     */
    protected $shippingTotal;

    /**
     * @var bool
     */
    protected $isNet;

    /**
     * @var bool
     */
    protected $isTaxFree;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var string
     */
    protected $payload;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CustomerStruct
     */
    protected $customer;

    /**
     * @var OrderStateStruct
     */
    protected $state;

    /**
     * @var PaymentMethodStruct
     */
    protected $paymentMethod;

    /**
     * @var CurrencyStruct
     */
    protected $currency;

    /**
     * @var \Shopware\Core\System\Touchpoint\TouchpointStruct
     */
    protected $touchpoint;

    /**
     * @var OrderAddressStruct
     */
    protected $billingAddress;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getTouchpointId(): string
    {
        return $this->touchpointId;
    }

    public function setTouchpointId(string $touchpointId): void
    {
        $this->touchpointId = $touchpointId;
    }

    public function getBillingAddressId(): string
    {
        return $this->billingAddressId;
    }

    public function setBillingAddressId(string $billingAddressId): void
    {
        $this->billingAddressId = $billingAddressId;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    public function setAmountTotal(float $amountTotal): void
    {
        $this->amountTotal = $amountTotal;
    }

    public function getPositionPrice(): float
    {
        return $this->positionPrice;
    }

    public function setPositionPrice(float $positionPrice): void
    {
        $this->positionPrice = $positionPrice;
    }

    public function getShippingTotal(): float
    {
        return $this->shippingTotal;
    }

    public function setShippingTotal(float $shippingTotal): void
    {
        $this->shippingTotal = $shippingTotal;
    }

    public function getIsNet(): bool
    {
        return $this->isNet;
    }

    public function setIsNet(bool $isNet): void
    {
        $this->isNet = $isNet;
    }

    public function getIsTaxFree(): bool
    {
        return $this->isTaxFree;
    }

    public function setIsTaxFree(bool $isTaxFree): void
    {
        $this->isTaxFree = $isTaxFree;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
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

    public function getCustomer(): CustomerStruct
    {
        return $this->customer;
    }

    public function setCustomer(CustomerStruct $customer): void
    {
        $this->customer = $customer;
    }

    public function getState(): OrderStateStruct
    {
        return $this->state;
    }

    public function setState(OrderStateStruct $state): void
    {
        $this->state = $state;
    }

    public function getPaymentMethod(): PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getCurrency(): CurrencyStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getTouchpoint(): TouchpointStruct
    {
        return $this->touchpoint;
    }

    public function setTouchpoint(TouchpointStruct $touchpoint): void
    {
        $this->touchpoint = $touchpoint;
    }

    public function getBillingAddress(): OrderAddressStruct
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(OrderAddressStruct $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }
}
