<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegistrationPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::REGISTRATION_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(RegistrationPageletRequestEvent $event): void
    {
        $registrationPageletRequest = $event->getRegistrationPageletRequest();
    }
}
