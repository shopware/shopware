<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddressPageletSubscriber implements EventSubscriberInterface
{
    public const GROUP_PARAMETER = 'group';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ADDRESS_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AddressPageletRequestEvent $event): void
    {
        $addressPageletRequest = $event->getAddressPageletRequest();
    }
}
