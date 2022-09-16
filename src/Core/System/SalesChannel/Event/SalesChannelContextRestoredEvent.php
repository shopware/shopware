<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextRestoredEvent extends NestedEvent
{
    /**
     * @var SalesChannelContext
     */
    protected $restoredContext;

    /**
     * @var SalesChannelContext|null
     */
    protected $currentContext;

    public function __construct(SalesChannelContext $restoredContext, SalesChannelContext $currentContext = null)
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

    public function getCurrentContext(): ?SalesChannelContext
    {
        return $this->currentContext;
    }
}
