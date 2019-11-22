<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope as RouteScopeAnnotation;
use Shopware\Core\Framework\Routing\Event\RouteScopeWhitlistCollectEvent;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RouteScopeListener implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var array
     */
    private $whitelistedControllers = [
        'Symfony\*',
    ];

    /**
     * @var RouteScopeRegistry
     */
    private $routeScopeRegistry;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        RouteScopeRegistry $routeScopeRegistry,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->routeScopeRegistry = $routeScopeRegistry;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;

        $whitlistCollectorEvent = new RouteScopeWhitlistCollectEvent($this->whitelistedControllers);
        $whitlistCollectorEvent = $eventDispatcher->dispatch(
            $whitlistCollectorEvent,
            RouteScopeEvents::ROUTE_SCOPE_WHITELIST_COLLECT
        );
        $this->whitelistedControllers = $whitlistCollectorEvent->getWhitelistedControllers();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['checkScope', -15],
            ],
        ];
    }

    public function checkScope(ControllerEvent $event): void
    {
        if ($this->requestStack->getParentRequest() !== null) {
            return;
        }

        if ($this->isWhitelistedController($event)) {
            return;
        }

        /** @var Request $masterRequest */
        $masterRequest = $this->requestStack->getMasterRequest();

        $routeScopeAnnotation = $masterRequest->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);
        if ($routeScopeAnnotation === null) {
            $routeScopeAnnotation = new RouteScopeAnnotation(['scopes' => ['default']]);
        }
        foreach ($routeScopeAnnotation->getScopes() as $routeScopeName) {
            /** @var RouteScope $routeScope */
            if (($routeScope = $this->routeScopeRegistry->getRouteScope($routeScopeName)) === null) {
                throw new InvalidRouteScopeException($masterRequest->attributes->get('_route'));
            } elseif ($routeScope->isAllowedPath($masterRequest->getPathInfo()) && $routeScope->isAllowed($masterRequest)) {
                return;
            }
        }

        throw new InvalidRouteScopeException($masterRequest->attributes->get('_route'));
    }

    private function getControllerClass(ControllerEvent $event): string
    {
        $controllerCallable = \Closure::fromCallable($event->getController());
        $controllerCallable = new \ReflectionFunction($controllerCallable);
        $controllerClass = get_class($controllerCallable->getClosureThis());

        return $controllerClass;
    }

    private function isWhitelistedController(ControllerEvent $event): bool
    {
        $controllerClass = $this->getControllerClass($event);
        foreach ($this->whitelistedControllers as $ignoredController) {
            if (strcmp($controllerClass, $ignoredController) === 0) {
                return true;
            }

            if (mb_substr($ignoredController, -1) !== '*') {
                continue;
            }

            if (strncmp($controllerClass, mb_substr($ignoredController, 0, -1), mb_strlen($ignoredController) - 1) === 0) {
                return true;
            }
        }

        return false;
    }
}
