<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Storefront\Event\AddressSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddressSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'address';

    public static function getSubscribedEvents()
    {
        return [
            AddressSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(AddressSaveRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getAddressSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
