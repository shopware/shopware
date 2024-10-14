<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class SalesChannelContextCreatedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @param array<string, mixed> $session
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

    public function getUsedToken(): string
    {
        return $this->usedToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
