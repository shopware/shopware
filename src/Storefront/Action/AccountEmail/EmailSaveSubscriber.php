<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountEmail;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'email';

    public static function getSubscribedEvents(): array
    {
        return [
            EmailSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(EmailSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getEmailSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
