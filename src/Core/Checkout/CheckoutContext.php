<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\ContextRulesLockedException;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;
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
     * @var CustomerGroupStruct
     */
    protected $currentCustomerGroup;

    /**
     * @var CustomerGroupStruct
     */
    protected $fallbackCustomerGroup;

    /**
     * @var CurrencyStruct
     */
    protected $currency;

    /**
     * @var SalesChannelStruct
     */
    protected $salesChannel;

    /**
     * @var TaxCollection
     */
    protected $taxRules;

    /**
     * @var CustomerStruct|null
     */
    protected $customer;

    /**
     * @var PaymentMethodStruct
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodStruct
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
     * @see CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE
     *
     * @var string
     */
    protected $taxState = CartPrice::TAX_STATE_GROSS;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var null|LanguageStruct
     */
    protected $fallbackLanguage;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $tenantId;

    public function __construct(
        string $tenantId,
        string $token,
        SalesChannelStruct $salesChannel,
        LanguageStruct $language,
        ?LanguageStruct $fallbackLanguage,
        CurrencyStruct $currency,
        CustomerGroupStruct $currentCustomerGroup,
        CustomerGroupStruct $fallbackCustomerGroup,
        TaxCollection $taxRules,
        PaymentMethodStruct $paymentMethod,
        ShippingMethodStruct $shippingMethod,
        ShippingLocation $shippingLocation,
        ?CustomerStruct $customer,
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
        $this->language = $language;
        $this->fallbackLanguage = $fallbackLanguage;
        $this->tenantId = $tenantId;
    }

    public function getCurrentCustomerGroup(): CustomerGroupStruct
    {
        return $this->currentCustomerGroup;
    }

    public function getFallbackCustomerGroup(): CustomerGroupStruct
    {
        return $this->fallbackCustomerGroup;
    }

    public function getCurrency(): CurrencyStruct
    {
        return $this->currency;
    }

    public function getSalesChannel(): SalesChannelStruct
    {
        return $this->salesChannel;
    }

    public function getTaxRules(): TaxCollection
    {
        return $this->taxRules;
    }

    public function getCustomer(): ?CustomerStruct
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }

    public function getContext(): Context
    {
        if ($this->context) {
            return $this->context;
        }

        $sourceContext = new SourceContext(SourceContext::ORIGIN_STOREFRONT_API);
        $sourceContext->setSalesChannelId($this->salesChannel->getId());

        return $this->context = new Context(
            $this->tenantId,
            $sourceContext,
            $this->salesChannel->getCatalogs()->getIds(),
            $this->rulesIds,
            $this->currency->getId(),
            $this->language->getId(),
            $this->language->getParentId(),
            Defaults::LIVE_VERSION,
            $this->currency->getFactor()
        );
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

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    public function getFallbackLanguage(): ?LanguageStruct
    {
        return $this->fallbackLanguage;
    }

    public function getTaxState(): string
    {
        return $this->taxState;
    }

    public function setTaxState(string $taxState): void
    {
        $this->taxState = $taxState;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
