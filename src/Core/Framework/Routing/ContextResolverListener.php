<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ContextResolverListener implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RequestContextResolverInterface
     */
    private $requestContextResolver;

    /**
     * @var bool
     */
    private $debug;

    public function __construct(
        RequestStack $requestStack,
        RequestContextResolverInterface $requestContextResolver,
        bool $debug
    ) {
        $this->requestStack = $requestStack;
        $this->requestContextResolver = $requestContextResolver;
        $this->debug = $debug;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['resolveContext', 5],
            ],
        ];
    }

    public function resolveContext(ControllerEvent $event): void
    {
        $this->requestContextResolver->resolve(
            $this->requestStack->getMasterRequest(),
            $event->getRequest()
        );
    }
}
