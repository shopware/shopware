<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class PasswordRecoveryUrlEvent extends Event implements SalesChannelAware, ShopwareSalesChannelEvent
{
    private string $recoveryUrl;

    private SalesChannelContext $salesChannelContext;

    private string $hash;

    private string $storefrontUrl;

    private CustomerRecoveryEntity $customerRecovery;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        string $recoveryUrl,
        string $hash,
        string $storefrontUrl,
        CustomerRecoveryEntity $customerRecovery
    ) {
        $this->recoveryUrl = $recoveryUrl;
        $this->salesChannelContext = $salesChannelContext;
        $this->hash = $hash;
        $this->storefrontUrl = $storefrontUrl;
        $this->customerRecovery = $customerRecovery;
    }

    public function getRecoveryUrl(): string
    {
        return $this->recoveryUrl;
    }

    public function setRecoveryUrl(string $recoveryUrl): void
    {
        $this->recoveryUrl = $recoveryUrl;
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

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getStorefrontUrl(): string
    {
        return $this->storefrontUrl;
    }

    public function getCustomerRecovery(): CustomerRecoveryEntity
    {
        return $this->customerRecovery;
    }
}
