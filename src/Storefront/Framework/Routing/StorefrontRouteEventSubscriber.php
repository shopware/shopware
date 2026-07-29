<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class StorefrontRouteEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => ['render', -10],
        ];
    }

    public function render(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.render');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.render');
        }
    }
}
