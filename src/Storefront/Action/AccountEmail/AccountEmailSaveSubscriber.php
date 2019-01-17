<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountEmail;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountEmailSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'email';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountEmailSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(AccountEmailSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getEmailSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
