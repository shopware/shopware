<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Storefront\Event\CheckoutEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartInfoPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutEvents::CARTINFO_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(CartInfoPageletRequestEvent $event): void
    {
        //$cartInfoPageletRequest = $event->getCartInfoPageletRequest();
    }
}
