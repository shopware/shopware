<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerConfirmRegisterUrlEvent extends Event implements SalesChannelAware, ShopwareSalesChannelEvent
{
    private string $confirmUrl;

    private SalesChannelContext $salesChannelContext;

    private string $emailHash;

    private ?string $customerHash;

    private CustomerEntity $customer;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        string $confirmUrl,
        string $emailHash,
        ?string $customerHash,
        CustomerEntity $customer
    ) {
        $this->confirmUrl = $confirmUrl;
        $this->salesChannelContext = $salesChannelContext;
        $this->emailHash = $emailHash;
        $this->customerHash = $customerHash;
        $this->customer = $customer;
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    public function setConfirmUrl(string $confirmUrl): void
    {
        $this->confirmUrl = $confirmUrl;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getEmailHash(): string
    {
        return $this->emailHash;
    }

    public function getCustomerHash(): ?string
    {
        return $this->customerHash;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
