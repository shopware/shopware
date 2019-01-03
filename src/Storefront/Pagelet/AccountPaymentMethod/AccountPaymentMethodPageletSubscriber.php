<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountPaymentMethodPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNT_PAYMENT_METHOD_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountPaymentMethodPageletRequestEvent $event): void
    {
        $accountPaymentMethodPageletRequest = $event->getAccountPaymentMethodPageletRequest();
    }
}
