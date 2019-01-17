<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountProfile;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountProfileSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'profile';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountProfileSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(AccountProfileSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getProfileSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
