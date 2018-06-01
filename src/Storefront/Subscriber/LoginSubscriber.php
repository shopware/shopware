<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Storefront\Event\LoginRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoginSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'login';

    public static function getSubscribedEvents()
    {
        return [
            LoginRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(LoginRequestEvent $event)
    {
        $request = $event->getRequest();
        $transformed = $event->getLoginRequest();

        $transformed->assign($request->request->get(self::PREFIX));
    }
}
