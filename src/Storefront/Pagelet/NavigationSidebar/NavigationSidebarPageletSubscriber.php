<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NavigationSidebarPageletSubscriber implements EventSubscriberInterface
{
    public const ROUTE_PARAMETER = '_route';

    public const ROUTE_PARAMS_PARAMETER = '_route_params';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::NAVIGATIONSIDEBAR_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(NavigationSidebarPageletRequestEvent $event): void
    {
        $navigationSidebarPageletRequest = $event->getNavigationSidebarPageletRequest();
        $navigationSidebarPageletRequest->setRoute($event->getRequest()->get(self::ROUTE_PARAMETER));
        $navigationSidebarPageletRequest->setRouteParams($event->getRequest()->get(self::ROUTE_PARAMS_PARAMETER));
        if (isset($event->getRequest()->get(self::ROUTE_PARAMS_PARAMETER, [])['id'])) {
            $navigationSidebarPageletRequest->setNavigationId($event->getRequest()->get(self::ROUTE_PARAMS_PARAMETER)['id']);
        }
    }
}
