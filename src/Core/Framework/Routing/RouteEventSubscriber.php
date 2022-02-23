<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RouteEventSubscriber implements EventSubscriberInterface
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST => ['request', -10],
            KernelEvents::RESPONSE => ['response', -10],
        ];

        if (class_exists(StorefrontRenderEvent::class)) {
            $events[StorefrontRenderEvent::class] = ['render', -10];
        }

        return $events;
    }

    public function request(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_route')) {
            return;
        }

        $name = $request->attributes->get('_route') . '.request';
        $this->dispatcher->dispatch($event, $name);
    }

    public function render(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_route')) {
            return;
        }

        $name = $request->attributes->get('_route') . '.render';
        $this->dispatcher->dispatch($event, $name);
    }

    public function response(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_route')) {
            return;
        }

        $name = $request->attributes->get('_route') . '.response';
        $this->dispatcher->dispatch($event, $name);
    }
}
