<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Framework\Struct\Struct;

class OrderConversionContext extends Struct
{
    /**
     * @var bool
     */
    protected $includeCustomer = true;

    /**
     * @var bool
     */
    protected $includeBillingAddress = true;

    /**
     * @var bool
     */
    protected $includeDeliveries = true;

    /**
     * @var bool
     */
    protected $includeTransactions = true;

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

    public function getApiAlias(): string
    {
        return 'cart_order_conversion_context';
    }
}
