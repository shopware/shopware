<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class SalesChannelContextResolvedEvent extends Event implements ShopwareSalesChannelEvent
{
    private SalesChannelContext $salesChannelContext;

    private string $usedToken;

    public function __construct(SalesChannelContext $salesChannelContext, string $usedToken)
    {
        $this->salesChannelContext = $salesChannelContext;
        $this->usedToken = $usedToken;
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
}
