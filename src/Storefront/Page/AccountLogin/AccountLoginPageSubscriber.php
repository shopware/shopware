<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountLoginPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTLOGIN_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountLoginPageRequestEvent $event): void
    {
        //$accountLoginPageRequest = $event->getAccountLoginPageRequest();
        //$accountLoginPageRequest->getAccountLoginRequest()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
