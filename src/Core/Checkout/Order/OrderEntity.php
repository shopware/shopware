<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use DateTime;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderCustomerId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var float
     */
    protected $currencyFactor;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $billingAddressId;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var CartPrice
     */
    protected $price;

    /**
     * @var float
     */
    protected $amountTotal;

    /**
     * @var float|null
     */
    protected $amountNet;

    /**
     * @var float
     */
    protected $positionPrice;

    /**
     * @var string
     */
    protected $taxStatus;

    /**
     * @var CalculatedPrice
     */
    protected $shippingCosts;

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
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderCustomerEntity
     */
    protected $orderCustomer;

    /**
     * @var PaymentMethodEntity
     */
    protected $paymentMethod;

    /**
     * @var CurrencyEntity
     */
    protected $currency;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var OrderAddressCollection
     */
    protected $addresses;

    /**
     * @var OrderDeliveryCollection|null
     */
    protected $deliveries;

    /**
     * @var OrderLineItemCollection|null
     */
    protected $lineItems;

    /**
     * @var OrderTransactionCollection|null
     */
    protected $transactions;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var SearchDocumentCollection|null
     */
    protected $searchKeywords;

    /**
     * @var StateMachineStateEntity
     */
    protected $stateMachineState;

    /**
     * @var string
     */
    protected $stateId;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getOrderCustomerId(): string
    {
        return $this->orderCustomerId;
    }

    public function setOrderCustomerId(string $orderCustomerId): void
    {
        $this->orderCustomerId = $orderCustomerId;
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

    public function getCurrencyFactor(): float
    {
        return $this->currencyFactor;
    }

    public function setCurrencyFactor(float $currencyFactor): void
    {
        $this->currencyFactor = $currencyFactor;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getBillingAddressId(): string
    {
        return $this->billingAddressId;
    }

    public function setBillingAddressId(string $billingAddressId): void
    {
        $this->billingAddressId = $billingAddressId;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    public function getPrice(): CartPrice
    {
        return $this->price;
    }

    public function setPrice(CartPrice $price): void
    {
        $this->price = $price;
    }

    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    public function getAmountNet(): float
    {
        return $this->amountNet;
    }

    public function getPositionPrice(): float
    {
        return $this->positionPrice;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(CalculatedPrice $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }

    public function getShippingTotal(): float
    {
        return $this->shippingTotal;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getOrderCustomer(): OrderCustomerEntity
    {
        return $this->orderCustomer;
    }

    public function setOrderCustomer(OrderCustomerEntity $orderCustomer): void
    {
        $this->orderCustomer = $orderCustomer;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getAddresses(): OrderAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(OrderAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
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

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getSearchKeywords(): ?SearchDocumentCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(?SearchDocumentCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }

    public function getStateMachineState(): StateMachineStateEntity
    {
        return $this->stateMachineState;
    }

    public function setStateMachineState(StateMachineStateEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
