<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Framework\Page\PageWithHeader;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class AccountOrderPage extends PageWithHeader
{
    /**
     * @var StorefrontSearchResult
     */
    protected $orders;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    public function getOrders(): StorefrontSearchResult
    {
        return $this->orders;
    }

    public function setOrders(StorefrontSearchResult $orders): void
    {
        $this->orders = $orders;
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
