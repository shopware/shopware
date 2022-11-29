<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @package core
 *
 * @internal Use SalesChannelContext for extensions
 */
class BaseContext
{
    protected CustomerGroupEntity $currentCustomerGroup;

    protected CurrencyEntity $currency;

    protected SalesChannelEntity $salesChannel;

    protected TaxCollection $taxRules;

    protected PaymentMethodEntity $paymentMethod;

    protected ShippingMethodEntity $shippingMethod;

    protected ShippingLocation $shippingLocation;

    protected Context $context;

    private CashRoundingConfig $itemRounding;

    private CashRoundingConfig $totalRounding;

    public function __construct(
        Context $baseContext,
        SalesChannelEntity $salesChannel,
        CurrencyEntity $currency,
        CustomerGroupEntity $currentCustomerGroup,
        TaxCollection $taxRules,
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod,
        ShippingLocation $shippingLocation,
        CashRoundingConfig $itemRounding,
        CashRoundingConfig $totalRounding
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->currency = $currency;
        $this->salesChannel = $salesChannel;
        $this->taxRules = $taxRules;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
        $this->context = $baseContext;
        $this->itemRounding = $itemRounding;
        $this->totalRounding = $totalRounding;
    }

    public function getCurrentCustomerGroup(): CustomerGroupEntity
    {
        return $this->currentCustomerGroup;
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

    public function getTaxState(): string
    {
        return $this->context->getTaxState();
    }

    public function getApiAlias(): string
    {
        return 'base_channel_context';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function getCurrencyId(): string
    {
        return $this->getCurrency()->getId();
    }
}
