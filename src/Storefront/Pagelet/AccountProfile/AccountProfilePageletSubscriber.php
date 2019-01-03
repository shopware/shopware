<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountProfilePageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTPROFILE_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountProfilePageletRequestEvent $event): void
    {
        $accountprofilePageletRequest = $event->getAccountProfilePageletRequest();
    }
}
