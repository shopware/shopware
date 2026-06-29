<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched in the kernel `controller` event, after the sales-channel context
 * has been resolved and stored on the request and before the matched controller
 * runs. The wrapped Symfony `ControllerEvent` is exposed so a listener can act on
 * the resolved context however it needs to — for example redirect a not-logged-in
 * customer via `getControllerEvent()->setController(...)`.
 */
#[Package('framework')]
class SalesChannelContextResolvedControllerEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly ControllerEvent $controllerEvent,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getControllerEvent(): ControllerEvent
    {
        return $this->controllerEvent;
    }

    public function getRequest(): Request
    {
        return $this->controllerEvent->getRequest();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
