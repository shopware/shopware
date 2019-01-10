<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountAddressPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTADDRESS_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountAddressPageRequestEvent $event): void
    {
        $accountAddressPageRequest = $event->getAccountAddressPageRequest();
        $accountAddressPageRequest->getAccountAddressRequest()->setAddressId($event->getHttpRequest()->attributes->get('addressId'));
    }
}
