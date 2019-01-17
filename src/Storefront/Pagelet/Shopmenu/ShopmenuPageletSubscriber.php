<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Shopmenu;

use Shopware\Storefront\Event\ShopmenuEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShopmenuPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ShopmenuEvents::SHOPMENU_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ShopmenuPageletRequestEvent $event): void
    {
        //$shopmenuPageletRequest = $event->getShopmenuPageletRequest();
    }
}
