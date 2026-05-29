<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type SalesChannelContextFactoryOptions from AbstractSalesChannelContextFactory
 */
#[Package('framework')]
class SalesChannelContextCreatedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @param ContextToken $usedToken
     * @param SalesChannelContextFactoryOptions $session
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $usedToken,
        private readonly array $session = []
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    /**
     * @return ContextToken
     */
    public function getUsedToken(): string
    {
        return $this->usedToken;
    }

    /**
     * @return SalesChannelContextFactoryOptions
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
