<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NavigationSubscriber implements EventSubscriberInterface
{
    public const ROUTE_PARAMETER = '_route';

    public const ROUTE_PARAMS_PARAMETER = '_route_params';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::NAVIGATION_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(NavigationPageletRequestEvent $event): void
    {
        $navigationPageletRequest = $event->getPageletRequest();
        $navigationPageletRequest->setRoute($event->getHttpRequest()->get(self::ROUTE_PARAMETER));
        $navigationPageletRequest->setRouteParams($event->getHttpRequest()->get(self::ROUTE_PARAMS_PARAMETER));
        if (isset($event->getHttpRequest()->get(self::ROUTE_PARAMS_PARAMETER, [])['id'])) {
            $navigationPageletRequest->setNavigationId($event->getHttpRequest()->get(self::ROUTE_PARAMS_PARAMETER)['id']);
        }
    }
}
