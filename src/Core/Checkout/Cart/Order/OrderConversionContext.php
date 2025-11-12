<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderConversionContext extends Struct
{
    protected bool $includeCustomer = true;

    protected bool $includeBillingAddress = true;

    protected bool $includeDeliveries = true;

    protected bool $includeTransactions = true;

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `includePersistentData` instead
     */
    protected bool $includeOrderDate = true;

    protected bool $includePersistentData = true;

    protected bool $includeOrderNumber = true;

    public function shouldIncludeCustomer(): bool
    {
        return $this->includeCustomer;
    }

    public function setIncludeCustomer(bool $includeCustomer): OrderConversionContext
    {
        $this->includeCustomer = $includeCustomer;

        return $this;
    }

    public function shouldIncludeBillingAddress(): bool
    {
        return $this->includeBillingAddress;
    }

    public function setIncludeBillingAddress(bool $includeBillingAddress): OrderConversionContext
    {
        $this->includeBillingAddress = $includeBillingAddress;

        return $this;
    }

    public function shouldIncludeDeliveries(): bool
    {
        return $this->includeDeliveries;
    }

    public function setIncludeDeliveries(bool $includeDeliveries): OrderConversionContext
    {
        $this->includeDeliveries = $includeDeliveries;

        return $this;
    }

    public function shouldIncludeTransactions(): bool
    {
        return $this->includeTransactions;
    }

    public function setIncludeTransactions(bool $includeTransactions): OrderConversionContext
    {
        $this->includeTransactions = $includeTransactions;

        return $this;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `shouldIncludePersistentData` instead
     */
    public function shouldIncludeOrderDate(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0',
            'The method "OrderConversionContext::shouldIncludeOrderDate" is deprecated and will be removed in v6.8.0. Use "shouldIncludePersistentData" instead.'
        );

        return $this->includeOrderDate;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `setIncludePersistentData` instead
     */
    public function setIncludeOrderDate(bool $includeOrderDate): OrderConversionContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0',
            'The method "OrderConversionContext::setIncludeOrderDate" is deprecated and will be removed in v6.8.0. Use "setIncludePersistentData" instead.'
        );

        $this->includeOrderDate = $includeOrderDate;
        $this->includePersistentData = $includeOrderDate;

        return $this;
    }

    public function shouldIncludePersistentData(): bool
    {
        return $this->includePersistentData;
    }

    public function setIncludePersistentData(bool $includePersistentData): OrderConversionContext
    {
        $this->includePersistentData = $includePersistentData;
        $this->includeOrderDate = $includePersistentData;

        return $this;
    }

    public function shouldIncludeOrderNumber(): bool
    {
        return $this->includeOrderNumber;
    }

    public function setIncludeOrderNumber(bool $includeOrderNumber): OrderConversionContext
    {
        $this->includeOrderNumber = $includeOrderNumber;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options)
    {
        /** @deprecated tag:v6.8.0 - remove overwrite of assign function */
        if (isset($options['includeOrderDate'])) {
            $options['includePersistentData'] = $options['includeOrderDate'];
        } elseif (isset($options['includePersistentData'])) {
            $options['includeOrderDate'] = $options['includePersistentData'];
        }

        return parent::assign($options);
    }

    public function getApiAlias(): string
    {
        return 'cart_order_conversion_context';
    }
}
