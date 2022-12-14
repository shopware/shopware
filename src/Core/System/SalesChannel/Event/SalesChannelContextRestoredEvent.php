<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package core
 */
class SalesChannelContextRestoredEvent extends NestedEvent
{
    private SalesChannelContext $restoredContext;

    private SalesChannelContext $currentContext;

    public function __construct(SalesChannelContext $restoredContext, SalesChannelContext $currentContext)
    {
        $this->restoredContext = $restoredContext;
        $this->currentContext = $currentContext;
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
