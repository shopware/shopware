<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

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
     * @var \DateTime
     */
    protected $date;

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
     * @var OrderCustomerEntity
     */
    protected $orderCustomer;

    /**
     * @var OrderStateEntity
     */
    protected $state;

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
     * @var OrderAddressEntity
     */
    protected $billingAddress;

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

    public function getOrderCustomerId(): string
    {
        return $this->orderCustomerId;
    }

    public function setOrderCustomerId(string $orderCustomerId): void
    {
        $this->orderCustomerId = $orderCustomerId;
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

    public function getAmountNet(): float
    {
        return $this->amountNet;
    }

    public function setAmountNet(float $amountNet): void
    {
        $this->amountNet = $amountNet;
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

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
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

    public function getState(): OrderStateEntity
    {
        return $this->state;
    }

    public function setState(OrderStateEntity $state): void
    {
        $this->state = $state;
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

    public function getBillingAddress(): OrderAddressEntity
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

    public function setBillingAddress(OrderAddressEntity $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getSearchKeywords(): ?SearchDocumentCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(?SearchDocumentCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }
}
