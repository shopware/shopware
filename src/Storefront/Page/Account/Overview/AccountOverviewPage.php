<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Page;

class AccountOverviewPage extends Page
{
    /**
     * @var OrderEntity|null
     */
    protected $newestOrder;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    public function setNewestOrder(OrderEntity $order): void
    {
        $this->newestOrder = $order;
    }

    public function getNewestOrder(): ?OrderEntity
    {
        return $this->newestOrder;
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
