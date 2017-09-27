<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Shop\Struct\ShopBasicStruct;

class OrderBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var \DateTime
     */
    protected $date;

    /**
     * @var string
     */
    protected $customerUuid;

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
     * @var string
     */
    protected $stateUuid;

    /**
     * @var string
     */
    protected $paymentMethodUuid;

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
    protected $currencyUuid;

    /**
     * @var string
     */
    protected $shopUuid;

    /**
     * @var string
     */
    protected $billingAddressUuid;

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
     * @var CustomerBasicStruct
     */
    protected $customer;

    /**
     * @var OrderStateBasicStruct
     */
    protected $state;

    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    /**
     * @var OrderAddressBasicStruct
     */
    protected $billingAddress;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

    public function getCustomerUuid(): string
    {
        return $this->customerUuid;
    }

    public function setCustomerUuid(string $customerUuid): void
    {
        $this->customerUuid = $customerUuid;
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

    public function getStateUuid(): string
    {
        return $this->stateUuid;
    }

    public function setStateUuid(string $stateUuid): void
    {
        $this->stateUuid = $stateUuid;
    }

    public function getPaymentMethodUuid(): string
    {
        return $this->paymentMethodUuid;
    }

    public function setPaymentMethodUuid(string $paymentMethodUuid): void
    {
        $this->paymentMethodUuid = $paymentMethodUuid;
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

    public function getCurrencyUuid(): string
    {
        return $this->currencyUuid;
    }

    public function setCurrencyUuid(string $currencyUuid): void
    {
        $this->currencyUuid = $currencyUuid;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getBillingAddressUuid(): string
    {
        return $this->billingAddressUuid;
    }

    public function setBillingAddressUuid(string $billingAddressUuid): void
    {
        $this->billingAddressUuid = $billingAddressUuid;
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

    public function getCustomer(): CustomerBasicStruct
    {
        return $this->customer;
    }

    public function setCustomer(CustomerBasicStruct $customer): void
    {
        $this->customer = $customer;
    }

    public function getState(): OrderStateBasicStruct
    {
        return $this->state;
    }

    public function setState(OrderStateBasicStruct $state): void
    {
        $this->state = $state;
    }

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }

    public function getBillingAddress(): OrderAddressBasicStruct
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(OrderAddressBasicStruct $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }
}
