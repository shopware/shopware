<?php declare(strict_types=1);

namespace Shopware\Framework\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApplicationSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RequestContextResolverInterface
     */
    private $requestContextResolver;

    public function __construct(
        RequestStack $requestStack,
        RequestContextResolverInterface $requestContextResolver
    ) {
        $this->requestStack = $requestStack;
        $this->requestContextResolver = $requestContextResolver;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['resolveContext', 5],
            ],
        ];
    }

    public function resolveContext(GetResponseEvent $event): void
    {
        $this->requestContextResolver->resolve(
            $this->requestStack->getMasterRequest(),
            $event->getRequest()
        );
    }
}
