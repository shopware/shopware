<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountLoginPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTLOGIN_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountLoginPageletRequestEvent $event): void
    {
        //$loginPageletRequest = $event->getLoginPageletRequest();
    }
}
