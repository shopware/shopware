<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Subscriber;

use Shopware\Storefront\Account\Event\RegistrationRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegistrationSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'register';

    public static function getSubscribedEvents()
    {
        return [
            RegistrationRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(RegistrationRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getRegistrationRequest();

        $data = $request->request->get(self::PREFIX);

        $transformed->assign($data);
        $transformed->setGuest((bool) $data['guest']);
    }
}
