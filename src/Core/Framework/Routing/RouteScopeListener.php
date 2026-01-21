<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class RouteScopeListener implements EventSubscriberInterface
{
    /**
     * @var RouteScopeWhitelistInterface[]
     */
    private readonly array $allowLists;

    /**
     * @internal
     *
     * @param iterable<RouteScopeWhitelistInterface> $allowLists
     */
    public function __construct(
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly RequestStack $requestStack,
        iterable $allowLists
    ) {
        $this->allowLists = \is_array($allowLists) ? $allowLists : iterator_to_array($allowLists);
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
        $mainRequest = $this->getMainRequest();

        foreach ($scopes as $routeScopeName) {
            $routeScope = $this->routeScopeRegistry->getRouteScope($routeScopeName);

            $pathAllowed = $routeScope->isAllowedPath($mainRequest->getPathInfo());
            $requestAllowed = $routeScope->isAllowed($mainRequest);

            if ($pathAllowed && $requestAllowed) {
                return;
            }
        }

        throw RoutingException::invalidRouteScope($mainRequest->attributes->get('_route'));
    }

    private function extractControllerClass(ControllerEvent $event): ?string
    {
        $controllerCallable = \Closure::fromCallable($event->getController());
        $controllerCallable = new \ReflectionFunction($controllerCallable);

        $controller = $controllerCallable->getClosureThis();

        if (!$controller) {
            return null;
        }

        return $controller::class;
    }

    private function isWhitelistedController(ControllerEvent $event): bool
    {
        $controllerClass = $this->extractControllerClass($event);

        if (!$controllerClass) {
            return false;
        }

        foreach ($this->allowLists as $whitelist) {
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

        /** @var list<string> $scopes */
        $scopes = $currentRequest->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if ($scopes !== []) {
            return $scopes;
        }

        throw RoutingException::invalidRouteScope($currentRequest->attributes->get('_route'));
    }

    private function getMainRequest(): Request
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if (!$mainRequest) {
            throw RoutingException::missingMainRequest();
        }

        return $mainRequest;
    }
}
