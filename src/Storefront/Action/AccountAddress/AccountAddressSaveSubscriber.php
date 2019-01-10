<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountAddress;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountAddressSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'address';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountAddressSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(AccountAddressSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getAddressSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
