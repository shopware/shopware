<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CheckoutPaymentMethod;

use Shopware\Storefront\Event\CheckoutEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutPaymentMethodPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutEvents::CHECKOUTPAYMENTMETHOD_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(CheckoutPaymentMethodPageletRequestEvent $event): void
    {
        //$checkoutPaymentMethodPageletRequest = $event->getCheckoutPaymentMethodPageletRequest();
    }
}
