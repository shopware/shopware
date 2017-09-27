<?php

namespace Shopware\Nexus\Extension;

use Shopware\Framework\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExtensionRegistrySubscriber implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['registerExtensionRegistry', 0],
            ],
        ];
    }

    public function registerExtensionRegistry(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(Router::REQUEST_TYPE_ATTRIBUTE)) {
            return $event;
        }

        $type = $request->attributes->get(Router::REQUEST_TYPE_ATTRIBUTE);
        if (Router::REQUEST_TYPE_NEXUS !== $type) {
            return $event;
        }

        $this->container->set(
            'shopware.extension.registry',
            $this->container->get('shopware.nexus.extension.registry')
        );

        return $event;
    }
}
