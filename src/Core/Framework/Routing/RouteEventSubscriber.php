<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class RouteEventSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST => ['request', -10],
            KernelEvents::CONTROLLER => ['controller', -10],
            KernelEvents::RESPONSE => ['response', -10],
        ];

        // @deprecated tag:v6.8.0 - StorefrontRenderEvent registration moves to Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber; remove this block (and the render() method) when v6.8.0.0 becomes default
        if (!Feature::isActive('v6.8.0.0')) {
            /** @phpstan-ignore phpat.restrictNamespacesInCore (Existence of Storefront dependency is checked before usage. Don't do that! Will be fixed with https://github.com/shopware/shopware/issues/12966) */
            if (class_exists(StorefrontRenderEvent::class)) {
                /** @phpstan-ignore phpat.restrictNamespacesInCore */
                $events[StorefrontRenderEvent::class] = ['render', -10];
            }
        }

        return $events;
    }

    public function request(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.request');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.request');
        }
    }

    /**
     * @deprecated tag:v6.8.0 - moved, {@see StorefrontRouteEventSubscriber::render()}
     *
     * @phpstan-ignore phpat.restrictNamespacesInCore (Existence of Storefront dependency is checked before usage. Don't do that! Will be fixed with https://github.com/shopware/shopware/issues/12966)
     */
    public function render(StorefrontRenderEvent $event): void
    {
        /** @phpstan-ignore phpat.restrictNamespacesInCore (Soft Storefront reference for the deprecation message; see issue #12966) */
        $replacement = StorefrontRouteEventSubscriber::class . '::render()';

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', $replacement)
        );

        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.render');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.render');
        }
    }

    public function response(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.response');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.response');
        }
    }

    public function controller(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.controller');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.controller');
        }
    }
}
