<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPagelet;

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

    protected array $formattingCustomerAddresses = [];

    /**
     * @internal (flag:FEATURE_NEXT_14001) remove comment on feature release
     */
    protected NewsletterAccountPagelet $newsletterAccountPagelet;

    public function getFormattingCustomerAddresses(): array
    {
        return $this->formattingCustomerAddresses;
    }

    public function setFormattingCustomerAddresses(array $formattingCustomerAddresses): ?array
    {
        return $this->formattingCustomerAddresses = $formattingCustomerAddresses;
    }

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

    /**
     * @internal (flag:FEATURE_NEXT_14001) remove comment on feature release
     */
    public function getNewsletterAccountPagelet(): NewsletterAccountPagelet
    {
        return $this->newsletterAccountPagelet;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14001) remove comment on feature release
     */
    public function setNewsletterAccountPagelet(NewsletterAccountPagelet $newsletterAccountPagelet): void
    {
        $this->newsletterAccountPagelet = $newsletterAccountPagelet;
    }
}
