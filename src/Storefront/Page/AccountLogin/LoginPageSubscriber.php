<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoginPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::LOGIN_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(LoginPageRequestEvent $event): void
    {
        $loginPageRequest = $event->getLoginPageRequest();
        $loginPageRequest->setRedirectTo($event->getRequest()->get('redirectTo'));
    }
}
