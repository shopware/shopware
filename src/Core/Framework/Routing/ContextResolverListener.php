<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @package core
 *
 * @internal
 */
class ContextResolverListener implements EventSubscriberInterface
{
    private RequestContextResolverInterface $requestContextResolver;

    /**
     * @internal
     */
    public function __construct(
        RequestContextResolverInterface $requestContextResolver
    ) {
        $this->requestContextResolver = $requestContextResolver;
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
        $this->requestContextResolver->resolve($event->getRequest());
    }
}
