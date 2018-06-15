<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Storefront\Event\EmailSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'email';

    public static function getSubscribedEvents()
    {
        return [
            EmailSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(EmailSaveRequestEvent $event)
    {
        $request = $event->getRequest();
        $transformed = $event->getEmailSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
