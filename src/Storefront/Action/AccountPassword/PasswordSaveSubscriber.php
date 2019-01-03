<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountPassword;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'password';

    public static function getSubscribedEvents(): array
    {
        return [
            PasswordSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(PasswordSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getPasswordSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
