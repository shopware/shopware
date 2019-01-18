<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

class AccountPaymentMethodPage extends Struct
{
    /**
     * @var EntitySearchResult
     */
    protected $paymentMethods;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    public function getPaymentMethods(): EntitySearchResult
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(EntitySearchResult $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
