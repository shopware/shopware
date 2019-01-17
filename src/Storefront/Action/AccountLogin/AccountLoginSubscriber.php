<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountLogin;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountLoginSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'login';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountLoginRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(AccountLoginRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getLoginRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
