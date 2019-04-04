<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\ContextRulesLockedException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CheckoutContext extends Struct
{
    /**
     * Unique token for context, e.g. stored in session or provided in request headers
     *
     * @var string
     */
    protected $token;

    /**
     * @var CustomerGroupEntity
     */
    protected $currentCustomerGroup;

    /**
     * @var CustomerGroupEntity
     */
    protected $fallbackCustomerGroup;

    /**
     * @var CurrencyEntity
     */
    protected $currency;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var TaxCollection
     */
    protected $taxRules;

    /**
     * @var CustomerEntity|null
     */
    protected $customer;

    /**
     * @var PaymentMethodEntity
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodEntity
     */
    protected $shippingMethod;

    /**
     * @var ShippingLocation
     */
    protected $shippingLocation;

    /**
     * @var array
     */
    protected $rulesIds;

    /**
     * @var bool
     */
    protected $rulesLocked = false;

    /**
     * @var Context
     */
    private $context;

    public function __construct(
        Context $baseContext,
        string $token,
        SalesChannelEntity $salesChannel,
        CurrencyEntity $currency,
        CustomerGroupEntity $currentCustomerGroup,
        CustomerGroupEntity $fallbackCustomerGroup,
        TaxCollection $taxRules,
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod,
        ShippingLocation $shippingLocation,
        ?CustomerEntity $customer,
        array $rulesIds = []
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
        $this->currency = $currency;
        $this->salesChannel = $salesChannel;
        $this->taxRules = $taxRules;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
        $this->rulesIds = $rulesIds;
        $this->token = $token;
        $this->context = $baseContext;
    }

    public function getCurrentCustomerGroup(): CustomerGroupEntity
    {
        return $this->currentCustomerGroup;
    }

    public function getFallbackCustomerGroup(): CustomerGroupEntity
    {
        return $this->fallbackCustomerGroup;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function getTaxRules(): TaxCollection
    {
        return $this->taxRules;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRuleIds(): array
    {
        return $this->rulesIds;
    }

    public function setRuleIds(array $ruleIds): void
    {
        if ($this->rulesLocked) {
            throw new ContextRulesLockedException();
        }

        $this->rulesIds = array_values($ruleIds);
    }

    public function lockRules(): void
    {
        $this->rulesLocked = true;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTaxState(): string
    {
        return $this->context->getTaxState();
    }

    public function setTaxState(string $taxState): void
    {
        $this->context->setTaxState($taxState);
    }
}
