<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
class SalesChannelContextRestoredEvent extends NestedEvent
{
    public function __construct(
        private readonly SalesChannelContext $restoredContext,
        private readonly SalesChannelContext $currentContext
    ) {
    }

    public function getRestoredSalesChannelContext(): SalesChannelContext
    {
        return $this->restoredContext;
    }

    public function getContext(): Context
    {
        return $this->restoredContext->getContext();
    }

    public function getCurrentSalesChannelContext(): SalesChannelContext
    {
        return $this->currentContext;
    }
}
