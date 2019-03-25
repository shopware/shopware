<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
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
            KernelEvents::REQUEST => [
                ['resolveContext', 5],
            ],
        ];
    }

    public function resolveContext(GetResponseEvent $event): void
    {
        try {
            $this->requestContextResolver->resolve(
                $this->requestStack->getMasterRequest(),
                $event->getRequest()
            );
        } catch (\Throwable $e) {
            $event->setResponse((new ErrorResponseFactory())->getResponseFromException($e, $this->debug));
        }
    }
}
