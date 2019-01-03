<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoginPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::LOGIN_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(LoginPageletRequestEvent $event): void
    {
        //$loginPageletRequest = $event->getLoginPageletRequest();
    }
}
