<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountOrderPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTORDER_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountOrderPageRequestEvent $event): void
    {
        //$accountOrderPageRequest = $event->getAccountOrderPageRequest();
        //$accountOrderPageRequest->getAccountOrderRequest()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
