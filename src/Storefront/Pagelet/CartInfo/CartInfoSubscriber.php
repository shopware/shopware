<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Storefront\Event\CheckoutEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartInfoSubscriber implements EventSubscriberInterface
{
    public const ROUTE_PARAMETER = '_route';

    public const ROUTE_PARAMS_PARAMETER = '_route_params';

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutEvents::CARTINFO_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(CartInfoPageletRequestEvent $event): void
    {
        $cartInfoPageletRequest = $event->getCartinfoPageletRequest();
    }
}
