<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Storefront\Event\AccountEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountOverviewPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AccountEvents::ACCOUNTOVERVIEW_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(AccountOverviewPageRequestEvent $event): void
    {
        $accountoverviewPageRequest = $event->getAccountOverviewPageRequest();
    }
}
