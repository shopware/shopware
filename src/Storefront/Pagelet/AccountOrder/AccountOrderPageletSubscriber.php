<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountOrderPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTORDER_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountOrderPageletRequestEvent $event): void
    {
        //$accountorderPageletRequest = $event->getAccountOrderPageletRequest();
    }
}
