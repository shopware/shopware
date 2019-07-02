<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextSwitchEvent extends NestedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var DataBag
     */
    private $requestDataBag;

    public function __construct(SalesChannelContext $context, DataBag $requestDataBag)
    {
        $this->salesChannelContext = $context;
        $this->requestDataBag = $requestDataBag;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getRequestDataBag(): DataBag
    {
        return $this->requestDataBag;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
