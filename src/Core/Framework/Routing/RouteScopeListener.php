<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope as RouteScopeAnnotation;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class RouteScopeListener implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    private RouteScopeRegistry $routeScopeRegistry;

    /**
     * @var RouteScopeWhitelistInterface[]
     */
    private array $whitelists;

    /**
     * @internal
     *
     * @param iterable<RouteScopeWhitelistInterface> $whitelists
     */
    public function __construct(
        RouteScopeRegistry $routeScopeRegistry,
        RequestStack $requestStack,
        iterable $whitelists
    ) {
        $this->routeScopeRegistry = $routeScopeRegistry;
        $this->requestStack = $requestStack;
        $this->whitelists = \is_array($whitelists) ? $whitelists : iterator_to_array($whitelists);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['checkScope', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
    }

    /**
     * Validate that any given controller invocation creates a valid scope with the original master request
     */
    public function checkScope(ControllerEvent $event): void
    {
        if ($this->isWhitelistedController($event)) {
            return;
        }

        $scopes = $this->extractCurrentScopeAnnotation($event);
        $masterRequest = $this->getMainRequest();

        foreach ($scopes as $routeScopeName) {
            $routeScope = $this->routeScopeRegistry->getRouteScope($routeScopeName);

            $pathAllowed = $routeScope->isAllowedPath($masterRequest->getPathInfo());
            $requestAllowed = $routeScope->isAllowed($masterRequest);

            if ($pathAllowed && $requestAllowed) {
                return;
            }
        }

        throw new InvalidRouteScopeException($masterRequest->attributes->get('_route'));
    }

    private function extractControllerClass(ControllerEvent $event): ?string
    {
        $controllerCallable = \Closure::fromCallable($event->getController());
        $controllerCallable = new \ReflectionFunction($controllerCallable);

        $controller = $controllerCallable->getClosureThis();

        if (!$controller) {
            return null;
        }

        return \get_class($controller);
    }

    private function isWhitelistedController(ControllerEvent $event): bool
    {
        $controllerClass = $this->extractControllerClass($event);

        if (!$controllerClass) {
            return false;
        }

        foreach ($this->whitelists as $whitelist) {
            if ($whitelist->applies($controllerClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function extractCurrentScopeAnnotation(ControllerEvent $event): array
    {
        $currentRequest = $event->getRequest();

        /** @var RouteScopeAnnotation|list<string> $scopes */
        $scopes = $currentRequest->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if ($scopes instanceof RouteScopeAnnotation) {
            return $scopes->getScopes();
        }

        if ($scopes !== []) {
            return $scopes;
        }

        throw new InvalidRouteScopeException($currentRequest->attributes->get('_route'));
    }

    private function getMainRequest(): Request
    {
        $masterRequest = $this->requestStack->getMainRequest();

        if (!$masterRequest) {
            throw new \InvalidArgumentException('Unable to check the request scope without master request');
        }

        return $masterRequest;
    }
}
