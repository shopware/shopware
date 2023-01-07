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
    /**
     * @deprecated tag:v6.5.0 - reason:visibility-change - will become private and natively typed to SalesChannelContext, use the getter instead
     *
     * @var SalesChannelContext
     */
    protected $restoredContext;

    /**
     * @deprecated tag:v6.5.0 - reason:visibility-change - will become private, use the getter instead
     */
    protected ?SalesChannelContext $currentContext;

    /**
     * @deprecated tag:v6.5.0 - Parameter $currentContext will be mandatory
     */
    public function __construct(SalesChannelContext $restoredContext, ?SalesChannelContext $currentContext = null)
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

    public function getCurrentSalesChannelContext(): ?SalesChannelContext
    {
        return $this->currentContext;
    }
}
