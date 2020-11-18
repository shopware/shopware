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

    public function __construct(SalesChannelContext $restoredContext)
    {
        $this->restoredContext = $restoredContext;
    }

    public function getRestoredSalesChannelContext(): SalesChannelContext
    {
        return $this->restoredContext;
    }

    public function getContext(): Context
    {
        return $this->restoredContext->getContext();
    }
}
