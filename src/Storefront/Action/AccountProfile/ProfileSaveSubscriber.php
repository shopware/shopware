<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountProfile;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProfileSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'profile';

    public static function getSubscribedEvents(): array
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
