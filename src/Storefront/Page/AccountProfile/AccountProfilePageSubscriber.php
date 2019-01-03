<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountProfilePageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTPROFILE_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountProfilePageRequestEvent $event): void
    {
        $accountprofilePageRequest = $event->getAccountProfilePageRequest();
    }
}
