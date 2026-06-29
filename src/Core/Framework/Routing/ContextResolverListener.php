<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedControllerEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class ContextResolverListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestContextResolverInterface $requestContextResolver,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['resolveContext', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE],
            ],
        ];
    }

    public function resolveContext(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $this->requestContextResolver->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $this->eventDispatcher->dispatch(new SalesChannelContextResolvedControllerEvent($event, $context));
    }
}
