<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountAddressPageletSubscriber implements EventSubscriberInterface
{
    public const GROUP_PARAMETER = 'group';

    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ADDRESS_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountAddressPageletRequestEvent $event): void
    {
        //$addressPageletRequest = $event->getAddressPageletRequest();
    }
}
