<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Application\Struct\ApplicationBasicStruct;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;

class OrderBasicStruct extends Entity
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
    protected $applicationId;

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
     * @var ApplicationBasicStruct
     */
    protected $application;

    /**
     * @var OrderAddressBasicStruct
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

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function setApplicationId(string $applicationId): void
    {
        $this->applicationId = $applicationId;
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

    public function getApplication(): ApplicationBasicStruct
    {
        return $this->application;
    }

    public function setApplication(ApplicationBasicStruct $application): void
    {
        $this->application = $application;
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
