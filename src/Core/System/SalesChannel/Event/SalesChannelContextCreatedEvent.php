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
    public function __construct(
        public readonly SalesChannelContext $context,
        public readonly string $usedToken,
        public readonly array $session = []
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Use `$event->context` instead
     */
    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    /**
     * @deprecated tag:v6.7.0 - Use `$event->context->getContext()` instead
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    /**
     * @deprecated tag:v6.7.0 - Use `$event->usedToken` instead
     */
    public function getUsedToken(): string
    {
        return $this->usedToken;
    }
}
