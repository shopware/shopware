<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Touchpoint\TouchpointStruct;

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
     * @var TouchpointStruct
     */
    protected $touchpoint;

    /**
     * @var OrderAddressStruct
     */
    protected $billingAddress;

    /**
     * @var null|OrderDeliveryCollection
     */
    protected $deliveries;

    /**
     * @var null|OrderLineItemCollection
     */
    protected $lineItems;

    /**
     * @var null|OrderTransactionCollection
     */
    protected $transactions;

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

    public function getDeliveries(): ?OrderDeliveryCollection
    {
        return $this->deliveries;
    }

    public function setDeliveries(OrderDeliveryCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    public function getLineItems(): ?OrderLineItemCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(OrderLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getTransactions(): ?OrderTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(OrderTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }
}
