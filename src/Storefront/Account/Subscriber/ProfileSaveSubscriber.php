<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Subscriber;

use Shopware\Storefront\Account\Event\ProfileSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProfileSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'profile';

    public static function getSubscribedEvents()
    {
        return [
            ProfileSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(ProfileSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getProfileSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
